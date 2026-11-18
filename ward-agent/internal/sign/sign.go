package sign

import (
	"crypto/ed25519"
	"encoding/base64"
	"fmt"
	"strconv"
)

func Message(version uint64, hash string) []byte {
	return []byte(strconv.FormatUint(version, 10) + "|" + hash)
}

func Verify(publicKeyB64, signatureB64 string, version uint64, hash string) error {
	if publicKeyB64 == "" || signatureB64 == "" {
		return fmt.Errorf("missing signature")
	}

	pub, err := base64.StdEncoding.DecodeString(publicKeyB64)
	if err != nil {
		return fmt.Errorf("public key: %w", err)
	}
	sig, err := base64.StdEncoding.DecodeString(signatureB64)
	if err != nil {
		return fmt.Errorf("signature: %w", err)
	}
	if len(pub) != ed25519.PublicKeySize {
		return fmt.Errorf("unexpected public key size")
	}
	if !ed25519.Verify(ed25519.PublicKey(pub), Message(version, hash), sig) {
		return fmt.Errorf("invalid policy signature")
	}
	return nil
}
