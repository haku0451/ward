package ipset

import (
	"net"
	"testing"
	"time"
)

func TestMatchAndProtection(t *testing.T) {
	exp := time.Now().Add(time.Hour)
	set := New([]Block{
		{ID: 1, CIDR: "203.0.113.0/24", ExpiresAt: &exp},
	}, []string{"198.51.100.10/32"})

	if set.Match(net.ParseIP("203.0.113.5"), "localhost") == nil {
		t.Fatal("expected denylist match")
	}
	if set.Match(net.ParseIP("198.51.100.10"), "localhost") != nil {
		t.Fatal("protected IP must not be blocked")
	}
	if set.Match(net.ParseIP("192.0.2.1"), "localhost") != nil {
		t.Fatal("unrelated IP must pass")
	}
}

func TestHostnameScopedBlock(t *testing.T) {
	set := New([]Block{
		{ID: 2, CIDR: "203.0.113.9/32", Hostname: "app.example.test"},
	}, nil)

	if set.Match(net.ParseIP("203.0.113.9"), "other.example.test") != nil {
		t.Fatal("site-scoped block must not apply to other hosts")
	}
	if set.Match(net.ParseIP("203.0.113.9"), "app.example.test") == nil {
		t.Fatal("site-scoped block must apply to matching host")
	}
}
