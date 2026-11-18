package sign

import (
	"crypto/ed25519"
	"encoding/base64"
	"testing"
)

func TestVerify(t *testing.T) {
	pub, priv, err := ed25519.GenerateKey(nil)
	if err != nil {
		t.Fatal(err)
	}
	hash := "abc"
	msg := Message(7, hash)
	sig := ed25519.Sign(priv, msg)
	pubB64 := base64.StdEncoding.EncodeToString(pub)
	sigB64 := base64.StdEncoding.EncodeToString(sig)

	if err := Verify(pubB64, sigB64, 7, hash); err != nil {
		t.Fatal(err)
	}
	if err := Verify(pubB64, sigB64, 8, hash); err == nil {
		t.Fatal("expected version mismatch to fail")
	}
}
