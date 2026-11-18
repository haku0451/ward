package clientip

import (
	"net"
	"net/http"
	"strings"
)

func FromRequest(r *http.Request, trusted []net.IPNet) string {
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		host = r.RemoteAddr
	}

	ip := net.ParseIP(host)
	if ip == nil {
		return host
	}

	if !isTrusted(ip, trusted) {
		return ip.String()
	}

	xff := r.Header.Get("X-Forwarded-For")
	if xff == "" {
		return ip.String()
	}

	parts := strings.Split(xff, ",")
	for i := len(parts) - 1; i >= 0; i-- {
		candidate := net.ParseIP(strings.TrimSpace(parts[i]))
		if candidate == nil {
			continue
		}
		if !isTrusted(candidate, trusted) {
			return candidate.String()
		}
	}

	return ip.String()
}

func ParseCIDRs(values []string) ([]net.IPNet, error) {
	var nets []net.IPNet
	for _, value := range values {
		if !strings.Contains(value, "/") {
			if ip := net.ParseIP(value); ip != nil {
				bits := 32
				if ip.To4() == nil {
					bits = 128
				}
				value = ip.String() + "/" + itoa(bits)
			}
		}
		_, network, err := net.ParseCIDR(value)
		if err != nil {
			return nil, err
		}
		nets = append(nets, *network)
	}
	return nets, nil
}

func isTrusted(ip net.IP, trusted []net.IPNet) bool {
	for _, network := range trusted {
		if network.Contains(ip) {
			return true
		}
	}
	return false
}

func itoa(v int) string {
	if v == 32 {
		return "32"
	}
	return "128"
}
