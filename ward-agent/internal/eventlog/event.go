package eventlog

import "time"

type Event struct {
	EventID        string    `json:"event_id"`
	OccurredAt     time.Time `json:"occurred_at"`
	Hostname       string    `json:"hostname,omitempty"`
	ClientIP       string    `json:"client_ip"`
	Method         string    `json:"method"`
	Path           string    `json:"path"`
	RuleID         string    `json:"rule_id,omitempty"`
	RuleMessage    string    `json:"rule_message,omitempty"`
	Action         string    `json:"action"`
	HTTPStatus     int       `json:"http_status,omitempty"`
	CorrelationID  string    `json:"correlation_id,omitempty"`
	Attempts       int       `json:"attempts,omitempty"`
	NextRetry      time.Time `json:"next_retry,omitempty"`
}
