package proxy

import (
	"crypto/rand"
	"fmt"
	"io"
	"log/slog"
	"net"
	"net/http"
	"net/http/httputil"
	"net/url"
	"strconv"
	"strings"
	"sync/atomic"
	"time"

	coraza "github.com/corazawaf/coraza/v3"
	"github.com/corazawaf/coraza/v3/types"

	"github.com/haku0451/ward/ward-agent/internal/clientip"
	"github.com/haku0451/ward/ward-agent/internal/config"
	"github.com/haku0451/ward/ward-agent/internal/eventlog"
	"github.com/haku0451/ward/ward-agent/internal/ipset"
	"github.com/haku0451/ward/ward-agent/internal/policy"
	"github.com/haku0451/ward/ward-agent/internal/queue"
	"github.com/haku0451/ward/ward-agent/internal/wafeng"
)

type Server struct {
	listen         string
	tls            config.TLS
	adminListen    string
	bodyLimit      int
	trusted        []net.IPNet
	queue          *queue.FileQueue
	logger         *slog.Logger
	version        string
	waf            atomic.Pointer[coraza.WAF]
	origins        atomic.Pointer[map[string]string]
	mode           atomic.Pointer[string]
	denylist       atomic.Pointer[ipset.Set]
	requests       atomic.Uint64
	blocked        atomic.Uint64
	deniedIP       atomic.Uint64
	httpServer     *http.Server
	adminServer    *http.Server
}

func New(cfg *config.File, store *policy.Store, q *queue.FileQueue, logger *slog.Logger, version string) (*Server, error) {
	trusted, err := clientip.ParseCIDRs(cfg.TrustedProxies)
	if err != nil {
		return nil, err
	}

	s := &Server{
		listen:      cfg.Listen,
		tls:         cfg.TLS,
		adminListen: cfg.AdminListen,
		bodyLimit:   cfg.WAF.RequestBodyLimit,
		trusted:     trusted,
		queue:       q,
		logger:      logger,
		version:     version,
	}

	if err := s.Apply(store.Current(), cfg.WAF); err != nil {
		return nil, err
	}

	return s, nil
}

func (s *Server) Apply(rev policy.Revision, wafCfg config.WAF) error {
	mode := rev.WAFMode
	if mode == "" {
		mode = wafCfg.Mode
	}
	wafCfg.Mode = mode

	waf, err := wafeng.New(wafCfg, nil)
	if err != nil {
		return err
	}

	origins := policy.OriginAllowlist(rev.Sites)
	protections := make([]string, 0, len(rev.Protections))
	for _, item := range rev.Protections {
		protections = append(protections, item.CIDR)
	}
	set := ipset.New(blocksFrom(rev), protections)
	s.waf.Store(&waf)
	s.origins.Store(&origins)
	s.mode.Store(&mode)
	s.denylist.Store(set)
	s.logger.Info("applied policy", "version", rev.Version, "hash", rev.Hash, "mode", mode, "sites", len(origins), "blocks", len(rev.Blocks))
	return nil
}

func (s *Server) Start() error {
	s.httpServer = &http.Server{
		Addr:              s.listen,
		Handler:           s,
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       15 * time.Second,
		WriteTimeout:      30 * time.Second,
		IdleTimeout:       60 * time.Second,
		MaxHeaderBytes:    16 * 1024,
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte("ok"))
	})
	mux.HandleFunc("/version", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = fmt.Fprintf(w, `{"version":%q}`, s.version)
	})
	mux.HandleFunc("/metrics", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = fmt.Fprintf(w, `{"requests":%d,"blocked":%d,"denied_ip":%d,"queue":%d}`,
			s.requests.Load(), s.blocked.Load(), s.deniedIP.Load(), s.queue.Len())
	})

	s.adminServer = &http.Server{
		Addr:              s.adminListen,
		Handler:           mux,
		ReadHeaderTimeout: 3 * time.Second,
	}

	go func() {
		s.logger.Info("admin listener", "addr", s.adminListen)
		if err := s.adminServer.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			s.logger.Error("admin server", "err", err)
		}
	}()

	s.logger.Info("proxy listener", "addr", s.listen)
	if s.tls.CertFile != "" && s.tls.KeyFile != "" {
		return s.httpServer.ListenAndServeTLS(s.tls.CertFile, s.tls.KeyFile)
	}
	return s.httpServer.ListenAndServe()
}

func (s *Server) Shutdown() {
	if s.httpServer != nil {
		_ = s.httpServer.Close()
	}
	if s.adminServer != nil {
		_ = s.adminServer.Close()
	}
}

func (s *Server) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	s.requests.Add(1)

	if isWebsocket(r) {
		http.Error(w, "websocket is not supported", http.StatusNotImplemented)
		return
	}

	if r.Body != nil {
		r.Body = http.MaxBytesReader(w, r.Body, int64(s.bodyLimit)+1)
	}

	wafPtr := s.waf.Load()
	if wafPtr == nil {
		http.Error(w, "waf unavailable", http.StatusServiceUnavailable)
		return
	}
	waf := *wafPtr

	tx := waf.NewTransaction()
	defer func() {
		tx.ProcessLogging()
		_ = tx.Close()
	}()

	corr := newID()
	w.Header().Set("X-Ward-Request-Id", corr)

	clientIP := clientip.FromRequest(r, s.trusted)
	host := hostnameOf(r.Host)
	if ip := net.ParseIP(clientIP); ip != nil {
		if set := s.denylist.Load(); set != nil {
			if match := set.Match(ip, host); match != nil {
				s.deniedIP.Add(1)
				s.blocked.Add(1)
				_ = s.queue.Enqueue(eventlog.Event{
					EventID:       newID(),
					OccurredAt:    time.Now().UTC(),
					Hostname:      host,
					ClientIP:      clientIP,
					Method:        r.Method,
					Path:          r.URL.RequestURI(),
					RuleID:        "ip-denylist",
					RuleMessage:   "IP/CIDR denylist " + match.CIDR,
					Action:        "block",
					HTTPStatus:    http.StatusForbidden,
					CorrelationID: corr,
				})
				w.WriteHeader(http.StatusForbidden)
				return
			}
		}
	}

	it, err := processRequest(tx, r, clientIP)
	if err != nil {
		s.logger.Error("waf request processing failed", "err", err)
		http.Error(w, "bad request", http.StatusBadRequest)
		return
	}

	mode := "detection_only"
	if m := s.mode.Load(); m != nil {
		mode = *m
	}

	if it != nil {
		status := it.Status
		if status == 0 {
			status = http.StatusForbidden
		}
		s.blocked.Add(1)
		s.record(tx, r, clientIP, corr, "block", status)
		w.WriteHeader(status)
		return
	}

	if rules := tx.MatchedRules(); len(rules) > 0 {
		action := "detect"
		if mode == "blocking" {
			action = "block"
			s.blocked.Add(1)
		}
		s.record(tx, r, clientIP, corr, action, http.StatusOK)
	}

	origin := s.originFor(r.Host)
	if origin == "" {
		http.Error(w, "origin is not allowlisted for this host", http.StatusMisdirectedRequest)
		return
	}

	target, err := url.Parse(origin)
	if err != nil {
		http.Error(w, "invalid origin", http.StatusBadGateway)
		return
	}

	proxy := httputil.NewSingleHostReverseProxy(target)
	proxy.ErrorHandler = func(rw http.ResponseWriter, req *http.Request, err error) {
		s.logger.Error("origin error", "err", err)
		http.Error(rw, "origin unavailable", http.StatusBadGateway)
	}
	director := proxy.Director
	proxy.Director = func(req *http.Request) {
		director(req)
		req.Host = target.Host
		req.Header.Set("X-Forwarded-For", clientIP)
		req.Header.Set("X-Ward-Request-Id", corr)
	}

	proxy.ServeHTTP(w, r)
}

func (s *Server) originFor(host string) string {
	hostname := host
	if h, _, err := net.SplitHostPort(host); err == nil {
		hostname = h
	}
	origins := s.origins.Load()
	if origins == nil {
		return ""
	}
	return (*origins)[hostname]
}

func (s *Server) record(tx types.Transaction, r *http.Request, clientIP, corr, action string, status int) {
	path := r.URL.RequestURI()
	if len(path) > 2048 {
		path = path[:2048]
	}

	rules := tx.MatchedRules()
	if len(rules) == 0 {
		_ = s.queue.Enqueue(eventlog.Event{
			EventID:       newID(),
			OccurredAt:    time.Now().UTC(),
			Hostname:      r.Host,
			ClientIP:      clientIP,
			Method:        r.Method,
			Path:          path,
			Action:        action,
			HTTPStatus:    status,
			CorrelationID: corr,
		})
		return
	}

	seen := map[int]struct{}{}
	for _, rule := range rules {
		id := rule.Rule().ID()
		if _, ok := seen[id]; ok {
			continue
		}
		seen[id] = struct{}{}
		msg := rule.Message()
		if len(msg) > 1024 {
			msg = msg[:1024]
		}
		_ = s.queue.Enqueue(eventlog.Event{
			EventID:       newID(),
			OccurredAt:    time.Now().UTC(),
			Hostname:      hostnameOf(r.Host),
			ClientIP:      clientIP,
			Method:        r.Method,
			Path:          path,
			RuleID:        strconv.Itoa(id),
			RuleMessage:   msg,
			Action:        action,
			HTTPStatus:    status,
			CorrelationID: corr,
		})
	}
}

func processRequest(tx types.Transaction, req *http.Request, client string) (*types.Interruption, error) {
	cport := 0
	host := client
	if h, p, err := net.SplitHostPort(req.RemoteAddr); err == nil {
		host = h
		cport, _ = strconv.Atoi(p)
	} else if client != "" {
		host = client
	}

	tx.ProcessConnection(host, cport, "", 0)
	tx.ProcessURI(req.URL.String(), req.Method, req.Proto)

	for k, vr := range req.Header {
		for _, v := range vr {
			tx.AddRequestHeader(k, v)
		}
	}
	if req.Host != "" {
		tx.AddRequestHeader("Host", req.Host)
		tx.SetServerName(req.Host)
	}

	if it := tx.ProcessRequestHeaders(); it != nil {
		return it, nil
	}

	if tx.IsRequestBodyAccessible() && req.Body != nil && req.Body != http.NoBody {
		it, _, err := tx.ReadRequestBodyFrom(req.Body)
		if err != nil {
			return nil, err
		}
		if it != nil {
			return it, nil
		}
		rbr, err := tx.RequestBodyReader()
		if err != nil {
			return nil, err
		}
		req.Body = io.NopCloser(io.MultiReader(rbr, req.Body))
	}

	return tx.ProcessRequestBody()
}

func isWebsocket(r *http.Request) bool {
	return strings.EqualFold(r.Header.Get("Upgrade"), "websocket")
}

func blocksFrom(rev policy.Revision) []ipset.Block {
	out := make([]ipset.Block, 0, len(rev.Blocks))
	for _, block := range rev.Blocks {
		item := ipset.Block{ID: block.ID, CIDR: block.CIDR, Hostname: hostnameOf(block.Hostname)}
		if block.ExpiresAt != "" {
			if parsed, err := time.Parse(time.RFC3339, block.ExpiresAt); err == nil {
				item.ExpiresAt = &parsed
			}
		}
		out = append(out, item)
	}
	return out
}

func hostnameOf(host string) string {
	if h, _, err := net.SplitHostPort(host); err == nil {
		return h
	}
	return host
}

func newID() string {
	var b [16]byte
	_, _ = io.ReadFull(rand.Reader, b[:])
	b[6] = (b[6] & 0x0f) | 0x40
	b[8] = (b[8] & 0x3f) | 0x80
	return fmt.Sprintf("%x-%x-%x-%x-%x", b[0:4], b[4:6], b[6:8], b[8:10], b[10:])
}
