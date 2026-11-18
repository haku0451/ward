package config

import (
	"fmt"
	"net/url"
	"os"
	"strings"
	"time"

	"gopkg.in/yaml.v3"
)

type File struct {
	Listen         string       `yaml:"listen"`
	AdminListen    string       `yaml:"admin_listen"`
	DataDir        string       `yaml:"data_dir"`
	Panel          Panel        `yaml:"panel"`
	WAF            WAF          `yaml:"waf"`
	Queue          Queue        `yaml:"queue"`
	TLS            TLS          `yaml:"tls"`
	TrustedProxies []string     `yaml:"trusted_proxies"`
	Sites          []LocalSite  `yaml:"sites"`
}

type Panel struct {
	URL            string        `yaml:"url"`
	Token          string        `yaml:"token"`
	TLSSkipVerify  bool          `yaml:"tls_skip_verify"`
	Timeout        time.Duration `yaml:"timeout"`
	HeartbeatEvery   time.Duration `yaml:"heartbeat_every"`
	RequireSigned    bool          `yaml:"require_signed_policy"`
	PolicyPublicKey  string        `yaml:"policy_public_key"`
}

type WAF struct {
	Mode              string        `yaml:"mode"`
	RequestBodyLimit  int           `yaml:"request_body_limit"`
	RequestBodyTimeout time.Duration `yaml:"request_body_timeout"`
}

type Queue struct {
	Path     string        `yaml:"path"`
	MaxBytes int64         `yaml:"max_bytes"`
	MaxAge      time.Duration `yaml:"max_age"`
	MaxAttempts int           `yaml:"max_attempts"`
}

type TLS struct {
	CertFile string `yaml:"cert_file"`
	KeyFile  string `yaml:"key_file"`
}

type LocalSite struct {
	Hostname  string `yaml:"hostname"`
	OriginURL string `yaml:"origin_url"`
	ID        int    `yaml:"id"`
}

func Load(path string) (*File, error) {
	raw, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	expanded := os.ExpandEnv(string(raw))

	var cfg File
	if err := yaml.Unmarshal([]byte(expanded), &cfg); err != nil {
		return nil, err
	}

	cfg.applyDefaults()
	if err := cfg.validate(); err != nil {
		return nil, err
	}

	return &cfg, nil
}

func (c *File) applyDefaults() {
	if c.Listen == "" {
		c.Listen = ":8080"
	}
	if c.AdminListen == "" {
		c.AdminListen = "127.0.0.1:9090"
	}
	if c.DataDir == "" {
		c.DataDir = "./data"
	}
	if c.WAF.Mode == "" {
		c.WAF.Mode = "detection_only"
	}
	if c.WAF.RequestBodyLimit == 0 {
		c.WAF.RequestBodyLimit = 13_107_200
	}
	if c.WAF.RequestBodyTimeout == 0 {
		c.WAF.RequestBodyTimeout = 10 * time.Second
	}
	if c.Panel.Timeout == 0 {
		c.Panel.Timeout = 10 * time.Second
	}
	if c.Panel.HeartbeatEvery == 0 {
		c.Panel.HeartbeatEvery = 30 * time.Second
	}
	if c.Queue.Path == "" {
		c.Queue.Path = c.DataDir + "/events.jsonl"
	}
	if c.Queue.MaxBytes == 0 {
		c.Queue.MaxBytes = 100 * 1024 * 1024
	}
	if c.Queue.MaxAge == 0 {
		c.Queue.MaxAge = 24 * time.Hour
	}
	if c.Queue.MaxAttempts == 0 {
		c.Queue.MaxAttempts = 8
	}
}

func (c *File) validate() error {
	mode := strings.ToLower(c.WAF.Mode)
	if mode != "detection_only" && mode != "blocking" {
		return fmt.Errorf("waf.mode must be detection_only or blocking")
	}
	c.WAF.Mode = mode

	if c.Panel.URL != "" {
		u, err := url.Parse(c.Panel.URL)
		if err != nil {
			return fmt.Errorf("panel.url: %w", err)
		}
		if u.Scheme != "https" && u.Scheme != "http" {
			return fmt.Errorf("panel.url must be http or https")
		}
		if u.Scheme != "https" && !c.Panel.TLSSkipVerify {
			// http is allowed for local development; production should use https
		}
	}

	for _, site := range c.Sites {
		if err := ValidateOrigin(site.OriginURL); err != nil {
			return fmt.Errorf("site %s: %w", site.Hostname, err)
		}
	}

	return nil
}

func ValidateOrigin(raw string) error {
	u, err := url.Parse(raw)
	if err != nil {
		return err
	}
	if u.Scheme != "http" && u.Scheme != "https" {
		return fmt.Errorf("origin must be http or https")
	}
	if u.Host == "" {
		return fmt.Errorf("origin host is required")
	}
	return nil
}
