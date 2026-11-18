package queue

import (
	"path/filepath"
	"testing"
	"time"

	"github.com/haku0451/ward/ward-agent/internal/eventlog"
)

func TestDedupAndRetry(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "events.jsonl")
	q, err := New(path, 1<<20, time.Hour, 3)
	if err != nil {
		t.Fatal(err)
	}

	event := eventlog.Event{EventID: "same", OccurredAt: time.Now().UTC(), ClientIP: "203.0.113.1", Method: "GET", Path: "/", Action: "detect"}
	if err := q.Enqueue(event); err != nil {
		t.Fatal(err)
	}
	if err := q.Enqueue(event); err != nil {
		t.Fatal(err)
	}

	drained, err := q.Drain(10)
	if err != nil {
		t.Fatal(err)
	}
	if len(drained) != 1 {
		t.Fatalf("dedup: got %d", len(drained))
	}

	if err := q.Requeue(drained); err != nil {
		t.Fatal(err)
	}

	again, err := q.Drain(10)
	if err != nil {
		t.Fatal(err)
	}
	if len(again) != 0 {
		t.Fatalf("expected backoff to keep event, got %d", len(again))
	}
}

func TestDropAfterMaxAttempts(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "events.jsonl")
	q, err := New(path, 1<<20, time.Hour, 1)
	if err != nil {
		t.Fatal(err)
	}

	event := eventlog.Event{EventID: "once", OccurredAt: time.Now().UTC(), ClientIP: "203.0.113.1", Method: "GET", Path: "/", Action: "detect"}
	if err := q.Enqueue(event); err != nil {
		t.Fatal(err)
	}

	drained, err := q.Drain(10)
	if err != nil {
		t.Fatal(err)
	}
	if len(drained) != 1 {
		t.Fatal("expected one event")
	}

	if err := q.Requeue(drained); err != nil {
		t.Fatal(err)
	}

	again, err := q.Drain(10)
	if err != nil {
		t.Fatal(err)
	}
	if len(again) != 0 {
		t.Fatalf("max attempts should drop, got %d", len(again))
	}
}
