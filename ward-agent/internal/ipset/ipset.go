package ipset

import (
	"net"
	"strings"
	"time"
)

type Block struct {
	ID        int
	CIDR      string
	Hostname  string
	ExpiresAt *time.Time
	network   *net.IPNet
}

type Set struct {
	blocks      []Block
	protections []*net.IPNet
}

func New(blocks []Block, protectionCIDRs []string) *Set {
	s := &Set{}
	for _, block := range blocks {
		n := parseCIDR(block.CIDR)
		if n == nil {
			continue
		}
		block.network = n
		s.blocks = append(s.blocks, block)
	}
	for _, cidr := range protectionCIDRs {
		if n := parseCIDR(cidr); n != nil {
			s.protections = append(s.protections, n)
		}
	}
	return s
}

func (s *Set) Protected(ip net.IP) bool {
	if s == nil || ip == nil {
		return false
	}
	for _, n := range s.protections {
		if n.Contains(ip) {
			return true
		}
	}
	return false
}

func (s *Set) Match(ip net.IP, hostname string) *Block {
	if s == nil || ip == nil || s.Protected(ip) {
		return nil
	}
	now := time.Now()
	for i := range s.blocks {
		block := &s.blocks[i]
		if block.ExpiresAt != nil && !block.ExpiresAt.IsZero() && now.After(*block.ExpiresAt) {
			continue
		}
		if block.Hostname != "" && !strings.EqualFold(block.Hostname, hostname) {
			continue
		}
		if block.network != nil && block.network.Contains(ip) {
			return block
		}
	}
	return nil
}

func parseCIDR(value string) *net.IPNet {
	value = strings.TrimSpace(value)
	if value == "" {
		return nil
	}
	if !strings.Contains(value, "/") {
		ip := net.ParseIP(value)
		if ip == nil {
			return nil
		}
		bits := 128
		if ip.To4() != nil {
			bits = 32
			value = ip.String() + "/32"
		} else {
			value = ip.String() + "/128"
		}
		_ = bits
	}
	_, network, err := net.ParseCIDR(value)
	if err != nil {
		return nil
	}
	return network
}
