package panel

import (
	"bytes"
	"crypto/tls"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"

	"github.com/haku0451/ward/ward-agent/internal/config"
	"github.com/haku0451/ward/ward-agent/internal/eventlog"
	"github.com/haku0451/ward/ward-agent/internal/policy"
)

type Client struct {
	baseURL string
	token   string
	http    *http.Client
}

type HeartbeatResponse struct {
	Status               string `json:"status"`
	DesiredConfigVersion uint64 `json:"desired_config_version"`
	DesiredConfigHash    string `json:"desired_config_hash"`
}

type ConfigResponse struct {
	Version    uint64        `json:"version"`
	Hash       string        `json:"hash"`
	Signature  string        `json:"signature"`
	PublicKey  string        `json:"public_key"`
	CRSVersion string        `json:"crs_version"`
	WAFMode    string        `json:"waf_mode"`
	Node       nodeInfo      `json:"node"`
	Payload    configPayload `json:"payload"`
}

type nodeInfo struct {
	ID   int    `json:"id"`
	UUID string `json:"uuid"`
}

type configPayload struct {
	Sites       []policy.Site       `json:"sites"`
	IPBlocks    []policy.Block      `json:"ip_blocks"`
	Protections []policy.Protection `json:"ip_protections"`
}

type ApplyResult struct {
	ConfigVersion uint64          `json:"config_version"`
	ConfigHash    string          `json:"config_hash"`
	OK            bool            `json:"ok"`
	Message       string          `json:"message,omitempty"`
	IPBlocks      []BlockResult   `json:"ip_blocks"`
}

type BlockResult struct {
	ID      int    `json:"id"`
	Status  string `json:"status"`
	Message string `json:"message,omitempty"`
}

func New(cfg config.Panel) *Client {
	transport := http.DefaultTransport.(*http.Transport).Clone()
	if cfg.TLSSkipVerify {
		transport.TLSClientConfig = &tls.Config{InsecureSkipVerify: true} //nolint:gosec // local development only
	}

	return &Client{
		baseURL: strings.TrimRight(cfg.URL, "/"),
		token:   cfg.Token,
		http: &http.Client{
			Timeout:   cfg.Timeout,
			Transport: transport,
		},
	}
}

func (c *Client) Enabled() bool {
	return c != nil && c.baseURL != "" && c.token != ""
}

func (c *Client) Heartbeat(version string, hostname string, rev policy.Revision) (*HeartbeatResponse, error) {
	body := map[string]any{
		"version":        version,
		"hostname":       hostname,
		"config_version": rev.Version,
		"config_hash":    rev.Hash,
	}

	var out HeartbeatResponse
	if err := c.do(http.MethodPost, "/api/agent/v1/heartbeat", body, &out); err != nil {
		return nil, err
	}
	return &out, nil
}

func (c *Client) Config() (*policy.Revision, error) {
	var out ConfigResponse
	if err := c.do(http.MethodGet, "/api/agent/v1/config", nil, &out); err != nil {
		return nil, err
	}

	mode := out.WAFMode
	if mode == "" {
		mode = "detection_only"
	}

	return &policy.Revision{
		Version:     out.Version,
		Hash:        out.Hash,
		Signature:   out.Signature,
		PublicKey:   out.PublicKey,
		CRSVersion:  out.CRSVersion,
		WAFMode:     mode,
		NodeID:      out.Node.ID,
		Sites:       out.Payload.Sites,
		Blocks:      out.Payload.IPBlocks,
		Protections: out.Payload.Protections,
	}, nil
}

func (c *Client) Events(events []eventlog.Event) error {
	if len(events) == 0 {
		return nil
	}

	body := map[string]any{"events": events}
	return c.do(http.MethodPost, "/api/agent/v1/events", body, nil)
}

func (c *Client) ApplyResults(result ApplyResult) error {
	return c.do(http.MethodPost, "/api/agent/v1/apply-results", result, nil)
}

func (c *Client) do(method, path string, payload any, dest any) error {
	var reader io.Reader
	if payload != nil {
		raw, err := json.Marshal(payload)
		if err != nil {
			return err
		}
		if len(raw) > 1_000_000 {
			return fmt.Errorf("request too large")
		}
		reader = bytes.NewReader(raw)
	}

	req, err := http.NewRequest(method, c.baseURL+path, reader)
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("Accept", "application/json")
	if payload != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	res, err := c.http.Do(req)
	if err != nil {
		return err
	}
	defer res.Body.Close()

	limited := io.LimitReader(res.Body, 2_000_000)
	raw, err := io.ReadAll(limited)
	if err != nil {
		return err
	}

	if res.StatusCode >= 300 {
		return fmt.Errorf("panel %s: %s", res.Status, strings.TrimSpace(string(raw)))
	}

	if dest == nil || len(raw) == 0 {
		return nil
	}

	return json.Unmarshal(raw, dest)
}
