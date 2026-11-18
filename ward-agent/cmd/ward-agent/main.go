package main

import (
	"flag"
	"fmt"
	"log/slog"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/haku0451/ward/ward-agent/internal/config"
	"github.com/haku0451/ward/ward-agent/internal/panel"
	"github.com/haku0451/ward/ward-agent/internal/policy"
	"github.com/haku0451/ward/ward-agent/internal/proxy"
	"github.com/haku0451/ward/ward-agent/internal/queue"
	"github.com/haku0451/ward/ward-agent/internal/sign"
)

const version = "0.3.0"

func main() {
	configPath := flag.String("config", "configs/agent.yaml", "path to agent config")
	flag.Parse()

	logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))

	cfg, err := config.Load(*configPath)
	if err != nil {
		logger.Error("load config", "err", err)
		os.Exit(1)
	}

	if envToken := os.Getenv("WARD_AGENT_TOKEN"); envToken != "" {
		cfg.Panel.Token = envToken
	}

	store, err := policy.NewStore(cfg.DataDir, cfg)
	if err != nil {
		logger.Error("policy store", "err", err)
		os.Exit(1)
	}

	q, err := queue.New(cfg.Queue.Path, cfg.Queue.MaxBytes, cfg.Queue.MaxAge, cfg.Queue.MaxAttempts)
	if err != nil {
		logger.Error("event queue", "err", err)
		os.Exit(1)
	}

	srv, err := proxy.New(cfg, store, q, logger, version)
	if err != nil {
		logger.Error("proxy", "err", err)
		os.Exit(1)
	}

	client := panel.New(cfg.Panel)

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, syscall.SIGINT, syscall.SIGTERM)

	go runSync(logger, cfg, client, store, srv, q)

	go func() {
		if err := srv.Start(); err != nil {
			logger.Error("proxy stopped", "err", err)
			os.Exit(1)
		}
	}()

	<-stop
	logger.Info("shutting down")
	srv.Shutdown()
}

func runSync(logger *slog.Logger, cfg *config.File, client *panel.Client, store *policy.Store, srv *proxy.Server, q *queue.FileQueue) {
	if !client.Enabled() {
		logger.Info("panel client disabled; serving last-good local policy")
		return
	}

	hostname, _ := os.Hostname()
	ticker := time.NewTicker(cfg.Panel.HeartbeatEvery)
	defer ticker.Stop()
	failures := 0

	syncOnce := func() {
		rev := store.Current()
		if _, err := client.Heartbeat(version, hostname, rev); err != nil {
			failures++
			logger.Warn("heartbeat failed; keeping last-good policy", "err", err, "failures", failures)
		} else {
			failures = 0
			next, err := client.Config()
			if err != nil {
				logger.Warn("config fetch failed; keeping last-good policy", "err", err)
			} else if err := acceptRevision(logger, cfg, store, srv, client, rev, *next); err != nil {
				logger.Warn("config rejected; keeping last-good policy", "err", err)
			}
		}

		events, err := q.Drain(200)
		if err != nil {
			logger.Warn("queue drain", "err", err)
			return
		}
		if len(events) == 0 {
			return
		}
		if err := client.Events(events); err != nil {
			logger.Warn("event upload failed; requeue", "err", err)
			_ = q.Requeue(events)
		}
	}

	syncOnce()
	for range ticker.C {
		if failures > 0 {
			backoff := min(time.Duration(1<<min(failures, 5))*cfg.Panel.HeartbeatEvery, 5*time.Minute)
			time.Sleep(backoff)
		}
		syncOnce()
	}
}

func acceptRevision(logger *slog.Logger, cfg *config.File, store *policy.Store, srv *proxy.Server, client *panel.Client, current, next policy.Revision) error {
	if next.Hash == current.Hash && next.Version == current.Version {
		return nil
	}

	if current.Version > 0 && next.Version < current.Version {
		return fmt.Errorf("replayed older policy version %d < %d", next.Version, current.Version)
	}

	pub := cfg.Panel.PolicyPublicKey
	if pub == "" {
		pub = next.PublicKey
	}

	if cfg.Panel.RequireSigned || (next.Signature != "" && pub != "") {
		if err := sign.Verify(pub, next.Signature, next.Version, next.Hash); err != nil {
			_ = reportApply(client, next, false, err.Error())
			return err
		}
	}

	previous := current
	if err := store.Save(next); err != nil {
		return err
	}
	if err := srv.Apply(next, cfg.WAF); err != nil {
		_ = store.Save(previous)
		_ = reportApply(client, next, false, err.Error())
		logger.Error("apply failed; restored last-good policy")
		return err
	}

	results := make([]panel.BlockResult, 0, len(next.Blocks))
	for _, block := range next.Blocks {
		results = append(results, panel.BlockResult{ID: block.ID, Status: "success"})
	}

	return reportApply(client, next, true, "", results...)
}

func reportApply(client *panel.Client, rev policy.Revision, ok bool, message string, blocks ...panel.BlockResult) error {
	if !client.Enabled() {
		return nil
	}
	return client.ApplyResults(panel.ApplyResult{
		ConfigVersion: rev.Version,
		ConfigHash:    rev.Hash,
		OK:            ok,
		Message:       message,
		IPBlocks:      blocks,
	})
}
