package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log/slog"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/gotd/td/session"
	"github.com/gotd/td/telegram"
	"github.com/gotd/td/tg"
)

// UserAccount represents a single MTProto user session.
type UserAccount struct {
	mu            sync.Mutex
	Phone         string
	ApiID         int
	ApiHash       string
	TwoFA         string
	ID            int64
	Name          string
	api           *tg.Client
	client        *telegram.Client
	ready         bool
	inUse         bool
	cooldownUntil time.Time
	floodUntil    time.Time
	lastUsed      time.Time
	sessionDir    string
	sessionJSON   string // path to pure JSON session file
	cancel        context.CancelFunc
}

// UserPool manages a pool of MTProto user accounts.
type UserPool struct {
	mu       sync.Mutex
	accounts []*UserAccount
}

func NewUserPool() *UserPool {
	return &UserPool{}
}

// LoadSessions loads session JSON files and starts connections.
// sessionsDir contains pure JSON files with auth_key_hex (no SQLite needed).
func (p *UserPool) LoadSessions(ctx context.Context, sessionsDir string) error {
	files, err := filepath.Glob(filepath.Join(sessionsDir, "*.json"))
	if err != nil {
		return fmt.Errorf("glob sessions: %w", err)
	}

	if len(files) == 0 {
		slog.Warn("no session files found", "dir", sessionsDir)
		return nil
	}

	for _, f := range files {
		data, err := os.ReadFile(f)
		if err != nil {
			slog.Error("read session", "file", f, "error", err)
			continue
		}

		var sj SessionJSON
		if err := json.Unmarshal(data, &sj); err != nil {
			slog.Error("parse session", "file", f, "error", err)
			continue
		}

		if sj.Phone == "" || sj.AuthKeyHex == "" {
			slog.Warn("skip invalid session", "file", f)
			continue
		}

		if sj.ApiID == 0 {
			sj.ApiID = 4
		}
		if sj.ApiHash == "" {
			sj.ApiHash = "014b35b6184100b085b0d0572f9b5103"
		}

		acct := &UserAccount{
			Phone:       sj.Phone,
			ApiID:       sj.ApiID,
			ApiHash:     sj.ApiHash,
			TwoFA:       sj.TwoFA,
			ID:          sj.ID,
			Name:        sj.FirstName,
			sessionJSON: f,
		}

		p.mu.Lock()
		p.accounts = append(p.accounts, acct)
		p.mu.Unlock()

		go func(a *UserAccount) {
			if err := a.Connect(ctx); err != nil {
				slog.Error("user account connect failed",
					"phone", a.Phone,
					"error", err,
				)
			}
		}(acct)
	}

	slog.Info("user pool loaded", "accounts", len(p.accounts))
	return nil
}

// Acquire gets an available user account. Returns nil if none available.
func (p *UserPool) Acquire() *UserAccount {
	p.mu.Lock()
	defer p.mu.Unlock()

	now := time.Now()
	var best *UserAccount

	for _, a := range p.accounts {
		a.mu.Lock()
		available := a.ready && !a.inUse && now.After(a.cooldownUntil) && now.After(a.floodUntil)
		a.mu.Unlock()

		if available {
			if best == nil || a.lastUsed.Before(best.lastUsed) {
				best = a
			}
		}
	}

	if best != nil {
		best.mu.Lock()
		best.inUse = true
		best.lastUsed = now
		best.mu.Unlock()
	}

	return best
}

// Release returns an account to the pool.
func (p *UserPool) Release(a *UserAccount) {
	a.mu.Lock()
	a.inUse = false
	a.mu.Unlock()
}

// SetFloodWait marks an account as flood-waited.
func (p *UserPool) SetFloodWait(a *UserAccount, duration time.Duration) {
	a.mu.Lock()
	a.floodUntil = time.Now().Add(duration)
	a.inUse = false
	a.mu.Unlock()
	slog.Warn("user account flood wait", "phone", a.Phone, "duration", duration)
}

// SetCooldown sets a cooldown on an account.
func (p *UserPool) SetCooldown(a *UserAccount, duration time.Duration) {
	a.mu.Lock()
	a.cooldownUntil = time.Now().Add(duration)
	a.inUse = false
	a.mu.Unlock()
}

// AvailableCount returns how many accounts are ready and not in cooldown/flood.
func (p *UserPool) AvailableCount() int {
	p.mu.Lock()
	defer p.mu.Unlock()
	now := time.Now()
	count := 0
	for _, a := range p.accounts {
		a.mu.Lock()
		if a.ready && !a.inUse && now.After(a.cooldownUntil) && now.After(a.floodUntil) {
			count++
		}
		a.mu.Unlock()
	}
	return count
}

func (p *UserPool) TotalCount() int {
	p.mu.Lock()
	defer p.mu.Unlock()
	return len(p.accounts)
}

// Connect establishes MTProto connection for a user account.
// Uses Telethon SQLite .session file or gotd file session.
func (a *UserAccount) Connect(ctx context.Context) error {
	ctx, cancel := context.WithCancel(ctx)
	a.cancel = cancel

	var storage session.Storage
	if a.sessionJSON != "" {
		storage = &JSONSessionStorage{Path: a.sessionJSON}
	} else {
		sessionPath := filepath.Join(a.sessionDir, a.Phone+".gotd.json")
		storage = &session.FileStorage{Path: sessionPath}
	}

	a.client = telegram.NewClient(a.ApiID, a.ApiHash, telegram.Options{
		SessionStorage: storage,
	})

	errCh := make(chan error, 1)
	go func() {
		errCh <- a.client.Run(ctx, func(ctx context.Context) error {
			status, err := a.client.Auth().Status(ctx)
			if err != nil {
				return fmt.Errorf("auth status: %w", err)
			}
			if !status.Authorized {
				// Session not authorized — needs interactive setup
				slog.Warn("user account not authorized, needs setup",
					"phone", a.Phone,
					"session", a.sessionJSON,
				)
				return fmt.Errorf("not authorized: run setup for %s", a.Phone)
			}

			a.mu.Lock()
			a.api = a.client.API()
			a.ready = true
			a.mu.Unlock()

			slog.Info("user account connected", "phone", a.Phone, "id", a.ID, "name", a.Name)

			<-ctx.Done()
			return ctx.Err()
		})
	}()

	// Wait a bit to see if connection succeeds
	select {
	case err := <-errCh:
		return err
	case <-time.After(10 * time.Second):
		// Check if ready
		a.mu.Lock()
		r := a.ready
		a.mu.Unlock()
		if !r {
			cancel()
			return fmt.Errorf("timeout connecting %s", a.Phone)
		}
		return nil
	}
}

// ResolveUsername resolves a username via user MTProto.
func (a *UserAccount) ResolveUsername(ctx context.Context, username string) (*InspectResult, error) {
	username = strings.TrimLeft(username, "@")

	resolved, err := a.api.ContactsResolveUsername(ctx, &tg.ContactsResolveUsernameRequest{
		Username: username,
	})
	if err != nil {
		return nil, err
	}

	for _, u := range resolved.Users {
		user, ok := u.(*tg.User)
		if !ok {
			continue
		}
		r := &InspectResult{
			OK: true, Source: "go_mtproto_user",
			Username: user.Username, Title: userFullName(user),
			IsBot: user.Bot,
		}
		if user.Bot {
			r.ChatType = "bot"
			r.Kind = "bot_start"
		} else {
			r.ChatType = "user"
			r.Kind = "public_username"
		}
		return r, nil
	}

	for _, c := range resolved.Chats {
		ch, ok := c.(*tg.Channel)
		if !ok {
			continue
		}
		r := &InspectResult{
			OK: true, Source: "go_mtproto_user",
			Username: ch.Username, Title: ch.Title, Kind: "public_username",
		}
		classifyChannel(ch, r)

		full, err := a.api.ChannelsGetFullChannel(ctx, &tg.InputChannel{
			ChannelID: ch.ID, AccessHash: ch.AccessHash,
		})
		if err == nil {
			if fc, ok := full.FullChat.(*tg.ChannelFull); ok {
				r.MemberCount = fc.ParticipantsCount
				if fc.SendPaidMessagesStars > 0 {
					r.IsPaidMessages = true
				}
			}
		}
		return r, nil
	}

	return nil, fmt.Errorf("@%s not found", username)
}

// CheckInvite checks an invite hash via user MTProto.
func (a *UserAccount) CheckInvite(ctx context.Context, hash string) (*InspectResult, error) {
	invite, err := a.api.MessagesCheckChatInvite(ctx, hash)
	if err != nil {
		return nil, err
	}
	return parseInviteResult(invite, "go_mtproto_user")
}
