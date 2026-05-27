package main

import (
	"context"
	"crypto/sha1"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"os"
	"sync"

	gotdsession "github.com/gotd/td/session"
)

// SessionJSON is the pure JSON format for sessions (no SQLite needed).
type SessionJSON struct {
	Phone         string `json:"phone"`
	DcID          int    `json:"dc_id"`
	ServerAddress string `json:"server_address"`
	Port          int    `json:"port"`
	AuthKeyHex    string `json:"auth_key_hex"`
	ApiID         int    `json:"api_id"`
	ApiHash       string `json:"api_hash"`
	TwoFA         string `json:"two_fa"`
	FirstName     string `json:"first_name"`
	ID            int64  `json:"id"`
}

// JSONSessionStorage loads auth key from a JSON file (no CGO/SQLite).
type JSONSessionStorage struct {
	Path   string
	mu     sync.Mutex
	mem    gotdsession.StorageMemory
	loaded bool
}

func (s *JSONSessionStorage) LoadSession(ctx context.Context) ([]byte, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.loaded {
		return s.mem.LoadSession(ctx)
	}

	raw, err := os.ReadFile(s.Path)
	if err != nil {
		return nil, fmt.Errorf("read session json: %w", err)
	}

	var sj SessionJSON
	if err := json.Unmarshal(raw, &sj); err != nil {
		return nil, fmt.Errorf("parse session json: %w", err)
	}

	authKey, err := hex.DecodeString(sj.AuthKeyHex)
	if err != nil {
		return nil, fmt.Errorf("decode auth key hex: %w", err)
	}

	if len(authKey) != 256 {
		return nil, fmt.Errorf("invalid auth key length: %d", len(authKey))
	}

	// AuthKeyID = SHA1(auth_key)[12:20]
	h := sha1.Sum(authKey)
	authKeyID := h[12:20]

	sd := &gotdsession.Data{
		DC:        sj.DcID,
		Addr:      fmt.Sprintf("%s:%d", sj.ServerAddress, sj.Port),
		AuthKey:   authKey,
		AuthKeyID: authKeyID,
	}

	loader := &gotdsession.Loader{Storage: &s.mem}
	if err := loader.Save(ctx, sd); err != nil {
		return nil, fmt.Errorf("save to mem: %w", err)
	}

	s.loaded = true
	return s.mem.LoadSession(ctx)
}

func (s *JSONSessionStorage) StoreSession(ctx context.Context, data []byte) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.loaded = true
	return s.mem.StoreSession(ctx, data)
}

var _ gotdsession.Storage = (*JSONSessionStorage)(nil)
