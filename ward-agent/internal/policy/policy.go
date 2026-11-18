package policy

import (
	"encoding/json"
	"os"
	"path/filepath"
	"sync"

	"github.com/haku0451/ward/ward-agent/internal/config"
)

type Revision struct {
	Version     uint64        `json:"version"`
	Hash        string        `json:"hash"`
	Signature   string        `json:"signature"`
	PublicKey   string        `json:"public_key"`
	CRSVersion  string        `json:"crs_version"`
	WAFMode     string        `json:"waf_mode"`
	NodeID      int           `json:"node_id"`
	Sites       []Site        `json:"sites"`
	Blocks      []Block       `json:"ip_blocks"`
	Protections []Protection  `json:"ip_protections"`
}

type Site struct {
	ID        int    `json:"id"`
	Name      string `json:"name"`
	Hostname  string `json:"hostname"`
	OriginURL string `json:"origin_url"`
	WAFMode   string `json:"waf_mode"`
	NodeID    *int   `json:"node_id"`
}

type Block struct {
	ID        int        `json:"id"`
	CIDR      string     `json:"cidr"`
	Source    string     `json:"source"`
	NodeID    *int       `json:"node_id"`
	SiteID    *int       `json:"site_id"`
	Hostname  string `json:"hostname"`
	ExpiresAt string `json:"expires_at"`
}

type Protection struct {
	ID   int    `json:"id"`
	CIDR string `json:"cidr"`
}

type Store struct {
	path string
	mu   sync.RWMutex
	rev  Revision
}

func NewStore(dataDir string, cfg *config.File) (*Store, error) {
	if err := os.MkdirAll(dataDir, 0o700); err != nil {
		return nil, err
	}

	s := &Store{path: filepath.Join(dataDir, "last-good-config.json")}
	if err := s.load(); err != nil && !os.IsNotExist(err) {
		return nil, err
	}

	if s.rev.Version == 0 && s.rev.Hash == "" {
		s.rev = Revision{
			Version:    0,
			Hash:       "local",
			CRSVersion: "bundled",
			WAFMode:    cfg.WAF.Mode,
			Sites:      localSites(cfg.Sites),
		}
		if err := s.saveLocked(); err != nil {
			return nil, err
		}
	}

	return s, nil
}

func (s *Store) Current() Revision {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.rev
}

func (s *Store) Save(rev Revision) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.rev = rev
	return s.saveLocked()
}

func (s *Store) load() error {
	raw, err := os.ReadFile(s.path)
	if err != nil {
		return err
	}

	s.mu.Lock()
	defer s.mu.Unlock()
	return json.Unmarshal(raw, &s.rev)
}

func (s *Store) saveLocked() error {
	raw, err := json.MarshalIndent(s.rev, "", "  ")
	if err != nil {
		return err
	}

	tmp := s.path + ".tmp"
	if err := os.WriteFile(tmp, raw, 0o600); err != nil {
		return err
	}

	return os.Rename(tmp, s.path)
}

func localSites(sites []config.LocalSite) []Site {
	out := make([]Site, 0, len(sites))
	for _, site := range sites {
		out = append(out, Site{
			ID:        site.ID,
			Hostname:  site.Hostname,
			OriginURL: site.OriginURL,
		})
	}
	return out
}

func OriginAllowlist(sites []Site) map[string]string {
	allowed := make(map[string]string, len(sites))
	for _, site := range sites {
		if err := config.ValidateOrigin(site.OriginURL); err != nil {
			continue
		}
		allowed[site.Hostname] = site.OriginURL
	}
	return allowed
}
