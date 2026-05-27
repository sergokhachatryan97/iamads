package main

import (
	"context"
	"fmt"
	"log/slog"
	"strings"
	"sync"

	"github.com/gotd/td/telegram"
	"github.com/gotd/td/tg"
)

// BotClient is a persistent MTProto bot connection.
// It connects once and reuses the connection for all requests.
type BotClient struct {
	botToken string
	api      *tg.Client
	ready    chan struct{}
	mu       sync.RWMutex
	running  bool
	cancel   context.CancelFunc
}

func NewBotClient(botToken string) *BotClient {
	return &BotClient{
		botToken: botToken,
		ready:    make(chan struct{}),
	}
}

// Start connects to Telegram MTProto and keeps the connection alive.
// Call this once at startup in a goroutine.
func (b *BotClient) Start(ctx context.Context) error {
	ctx, cancel := context.WithCancel(ctx)
	b.cancel = cancel

	client := telegram.NewClient(6, "eb06d4abfb49dc3eeb1aeb98ae0f581e", telegram.Options{})

	return client.Run(ctx, func(ctx context.Context) error {
		status, err := client.Auth().Status(ctx)
		if err != nil {
			return fmt.Errorf("auth status: %w", err)
		}
		if !status.Authorized {
			if _, err := client.Auth().Bot(ctx, b.botToken); err != nil {
				return fmt.Errorf("bot auth: %w", err)
			}
		}

		b.mu.Lock()
		b.api = client.API()
		b.running = true
		b.mu.Unlock()

		close(b.ready)
		slog.Info("bot_client connected", "token_prefix", b.botToken[:10]+"...")

		<-ctx.Done()
		return ctx.Err()
	})
}

func (b *BotClient) Stop() {
	if b.cancel != nil {
		b.cancel()
	}
}

func (b *BotClient) IsReady() bool {
	b.mu.RLock()
	defer b.mu.RUnlock()
	return b.running && b.api != nil
}

func (b *BotClient) WaitReady(ctx context.Context) error {
	select {
	case <-b.ready:
		return nil
	case <-ctx.Done():
		return ctx.Err()
	}
}

// ResolveUsername resolves a public username via MTProto bot.
func (b *BotClient) ResolveUsername(ctx context.Context, username string) (*InspectResult, error) {
	if !b.IsReady() {
		return nil, fmt.Errorf("bot client not ready")
	}

	username = strings.TrimLeft(username, "@")

	resolved, err := b.api.ContactsResolveUsername(ctx, &tg.ContactsResolveUsernameRequest{
		Username: username,
	})
	if err != nil {
		return nil, fmt.Errorf("resolve @%s: %w", username, err)
	}

	// User (bot or human)
	for _, u := range resolved.Users {
		user, ok := u.(*tg.User)
		if !ok {
			continue
		}
		r := &InspectResult{
			OK:       true,
			Source:   "go_mtproto_bot",
			Username: user.Username,
			Title:    userFullName(user),
			IsBot:    user.Bot,
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

	// Channel / supergroup
	for _, c := range resolved.Chats {
		ch, ok := c.(*tg.Channel)
		if !ok {
			continue
		}
		r := &InspectResult{
			OK:       true,
			Source:   "go_mtproto_bot",
			Username: ch.Username,
			Title:    ch.Title,
			Kind:     "public_username",
		}
		classifyChannel(ch, r)

		// Get full info
		full, err := b.api.ChannelsGetFullChannel(ctx, &tg.InputChannel{
			ChannelID:  ch.ID,
			AccessHash: ch.AccessHash,
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

	return nil, fmt.Errorf("@%s not found in resolved result", username)
}

// GetPostInfo gets views/poll info for a specific post.
func (b *BotClient) GetPostInfo(ctx context.Context, username string, postID int) (*InspectResult, error) {
	if !b.IsReady() {
		return nil, fmt.Errorf("bot client not ready")
	}

	username = strings.TrimLeft(username, "@")

	// Resolve channel first
	resolved, err := b.api.ContactsResolveUsername(ctx, &tg.ContactsResolveUsernameRequest{
		Username: username,
	})
	if err != nil {
		return nil, fmt.Errorf("resolve @%s: %w", username, err)
	}

	var ic *tg.InputChannel
	for _, c := range resolved.Chats {
		if ch, ok := c.(*tg.Channel); ok {
			ic = &tg.InputChannel{ChannelID: ch.ID, AccessHash: ch.AccessHash}
			break
		}
	}
	if ic == nil {
		return nil, fmt.Errorf("channel @%s not found", username)
	}

	msgs, err := b.api.ChannelsGetMessages(ctx, &tg.ChannelsGetMessagesRequest{
		Channel: ic,
		ID:      []tg.InputMessageClass{&tg.InputMessageID{ID: postID}},
	})
	if err != nil {
		return nil, fmt.Errorf("get post %d: %w", postID, err)
	}

	var messages []tg.MessageClass
	switch v := msgs.(type) {
	case *tg.MessagesChannelMessages:
		messages = v.Messages
	case *tg.MessagesMessages:
		messages = v.Messages
	case *tg.MessagesMessagesSlice:
		messages = v.Messages
	}

	if len(messages) == 0 {
		return nil, fmt.Errorf("post %d not found", postID)
	}

	msg, ok := messages[0].(*tg.Message)
	if !ok {
		return nil, fmt.Errorf("post %d is not a message", postID)
	}

	r := &InspectResult{
		OK:        true,
		Source:    "go_mtproto_bot",
		Kind:      "public_post",
		ChatType:  "channel",
		IsChannel: true,
		Username:  username,
		PostID:    postID,
		Views:     msg.Views,
	}

	if msg.Media != nil {
		if _, isPoll := msg.Media.(*tg.MessageMediaPoll); isPoll {
			r.IsPoll = true
		}
	}

	return r, nil
}

// CheckInvite checks an invite hash.
func (b *BotClient) CheckInvite(ctx context.Context, hash string) (*InspectResult, error) {
	if !b.IsReady() {
		return nil, fmt.Errorf("bot client not ready")
	}

	invite, err := b.api.MessagesCheckChatInvite(ctx, hash)
	if err != nil {
		return nil, fmt.Errorf("check invite: %w", err)
	}

	return parseInviteResult(invite, "go_mtproto_bot")
}

func classifyChannel(ch *tg.Channel, r *InspectResult) {
	if ch.Megagroup {
		r.ChatType = "supergroup"
		r.AudienceType = "members"
		r.IsGroup = true
	} else if ch.Broadcast {
		r.ChatType = "channel"
		r.AudienceType = "subscribers"
		r.IsChannel = true
	} else {
		r.ChatType = "group"
		r.AudienceType = "members"
		r.IsGroup = true
	}
}

func parseInviteResult(invite tg.ChatInviteClass, source string) (*InspectResult, error) {
	r := &InspectResult{OK: true, Source: source, Kind: "invite", IsInvite: true}

	switch v := invite.(type) {
	case *tg.ChatInvite:
		r.Title = v.Title
		r.MemberCount = v.ParticipantsCount
		if v.Broadcast && !v.Megagroup {
			r.ChatType = "channel"
			r.AudienceType = "subscribers"
			r.IsChannel = true
		} else if v.Megagroup {
			r.ChatType = "supergroup"
			r.AudienceType = "members"
			r.IsGroup = true
		} else {
			r.ChatType = "group"
			r.AudienceType = "members"
			r.IsGroup = true
		}

	case *tg.ChatInviteAlready:
		switch c := v.Chat.(type) {
		case *tg.Channel:
			r.Title = c.Title
			classifyChannel(c, r)
		case *tg.Chat:
			r.Title = c.Title
			r.MemberCount = c.ParticipantsCount
			r.ChatType = "group"
			r.AudienceType = "members"
			r.IsGroup = true
		}

	case *tg.ChatInvitePeek:
		switch c := v.Chat.(type) {
		case *tg.Channel:
			r.Title = c.Title
			classifyChannel(c, r)
		}

	default:
		return nil, fmt.Errorf("unexpected invite type")
	}

	return r, nil
}

func userFullName(u *tg.User) string {
	n := u.FirstName
	if u.LastName != "" {
		n += " " + u.LastName
	}
	return n
}
