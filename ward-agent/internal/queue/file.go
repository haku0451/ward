package queue

import (
	"bufio"
	"encoding/json"
	"os"
	"path/filepath"
	"sync"
	"time"

	"github.com/haku0451/ward/ward-agent/internal/eventlog"
)

type FileQueue struct {
	path        string
	maxBytes    int64
	maxAge      time.Duration
	maxAttempts int
	mu          sync.Mutex
	seen        map[string]struct{}
}

func New(path string, maxBytes int64, maxAge time.Duration, maxAttempts int) (*FileQueue, error) {
	if err := os.MkdirAll(filepath.Dir(path), 0o700); err != nil {
		return nil, err
	}

	f, err := os.OpenFile(path, os.O_CREATE, 0o600)
	if err != nil {
		return nil, err
	}
	_ = f.Close()

	if maxAttempts < 1 {
		maxAttempts = 8
	}

	q := &FileQueue{
		path:        path,
		maxBytes:    maxBytes,
		maxAge:      maxAge,
		maxAttempts: maxAttempts,
		seen:        map[string]struct{}{},
	}
	_ = q.loadSeen()
	return q, nil
}

func (q *FileQueue) Len() int {
	q.mu.Lock()
	defer q.mu.Unlock()
	return len(q.seen)
}

func (q *FileQueue) Enqueue(event eventlog.Event) error {
	q.mu.Lock()
	defer q.mu.Unlock()

	if event.EventID != "" {
		if _, ok := q.seen[event.EventID]; ok {
			return nil
		}
		q.seen[event.EventID] = struct{}{}
	}

	if err := q.rotateIfNeeded(); err != nil {
		return err
	}

	f, err := os.OpenFile(q.path, os.O_APPEND|os.O_WRONLY, 0o600)
	if err != nil {
		return err
	}
	defer f.Close()

	enc := json.NewEncoder(f)
	return enc.Encode(event)
}

func (q *FileQueue) Drain(limit int) ([]eventlog.Event, error) {
	q.mu.Lock()
	defer q.mu.Unlock()

	f, err := os.Open(q.path)
	if err != nil {
		if os.IsNotExist(err) {
			return nil, nil
		}
		return nil, err
	}

	var kept []eventlog.Event
	var drained []eventlog.Event
	cutoff := time.Now().Add(-q.maxAge)
	now := time.Now()
	seen := map[string]struct{}{}

	scanner := bufio.NewScanner(f)
	scanner.Buffer(make([]byte, 0, 64*1024), 1024*1024)

	for scanner.Scan() {
		line := scanner.Bytes()
		if len(line) == 0 {
			continue
		}

		var event eventlog.Event
		if err := json.Unmarshal(line, &event); err != nil {
			continue
		}

		if event.EventID != "" {
			if _, ok := seen[event.EventID]; ok {
				continue
			}
			seen[event.EventID] = struct{}{}
		}

		if !event.OccurredAt.IsZero() && event.OccurredAt.Before(cutoff) {
			continue
		}

		if event.Attempts >= q.maxAttempts {
			continue
		}

		if !event.NextRetry.IsZero() && event.NextRetry.After(now) {
			kept = append(kept, event)
			continue
		}

		if len(drained) < limit {
			drained = append(drained, event)
			continue
		}

		kept = append(kept, event)
	}

	scanErr := scanner.Err()
	_ = f.Close()
	if scanErr != nil {
		return nil, scanErr
	}

	if err := q.rewrite(kept); err != nil {
		return nil, err
	}

	q.seen = seen
	for _, event := range kept {
		if event.EventID != "" {
			q.seen[event.EventID] = struct{}{}
		}
	}

	return drained, nil
}

func (q *FileQueue) Requeue(events []eventlog.Event) error {
	q.mu.Lock()
	defer q.mu.Unlock()

	f, err := os.OpenFile(q.path, os.O_APPEND|os.O_WRONLY|os.O_CREATE, 0o600)
	if err != nil {
		return err
	}
	defer f.Close()

	enc := json.NewEncoder(f)
	for _, event := range events {
		event.Attempts++
		delay := time.Duration(1<<min(event.Attempts, 6)) * time.Second
		event.NextRetry = time.Now().Add(delay)
		if event.EventID != "" {
			q.seen[event.EventID] = struct{}{}
		}
		if err := enc.Encode(event); err != nil {
			return err
		}
	}

	return nil
}

func (q *FileQueue) rotateIfNeeded() error {
	info, err := os.Stat(q.path)
	if err != nil {
		if os.IsNotExist(err) {
			return nil
		}
		return err
	}

	if info.Size() < q.maxBytes {
		return nil
	}

	backup := q.path + ".old"
	_ = os.Remove(backup)
	return os.Rename(q.path, backup)
}

func (q *FileQueue) rewrite(events []eventlog.Event) error {
	tmp := q.path + ".tmp"
	f, err := os.OpenFile(tmp, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, 0o600)
	if err != nil {
		return err
	}

	enc := json.NewEncoder(f)
	for _, event := range events {
		if err := enc.Encode(event); err != nil {
			_ = f.Close()
			return err
		}
	}

	if err := f.Close(); err != nil {
		return err
	}

	if err := os.Remove(q.path); err != nil && !os.IsNotExist(err) {
		return err
	}

	return os.Rename(tmp, q.path)
}

func (q *FileQueue) loadSeen() error {
	f, err := os.Open(q.path)
	if err != nil {
		return err
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		var event eventlog.Event
		if json.Unmarshal(scanner.Bytes(), &event) == nil && event.EventID != "" {
			q.seen[event.EventID] = struct{}{}
		}
	}
	return scanner.Err()
}
