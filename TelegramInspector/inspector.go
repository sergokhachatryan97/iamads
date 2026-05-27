package main

import (
	"context"
	"fmt"
	"log/slog"
	"strings"
	"time"
)

// Inspector orchestrates the inspection chain:
// 1) User MTProto pool → 2) Bot MTProto (optional) → 3) HTML parser
type Inspector struct {
	bot   *BotClient
	pool  *UserPool
	html  *HtmlInspector
	cache *Cache
}

func NewInspector(bot *BotClient, pool *UserPool) *Inspector {
	return &Inspector{
		bot:   bot,
		pool:  pool,
		html:  NewHtmlInspector(),
		cache: NewCache(5 * time.Minute),
	}
}

func (ins *Inspector) Inspect(ctx context.Context, req InspectRequest) InspectResult {
	parsed := ParseLink(req.Link)

	if parsed.Kind == KindUnknown {
		return failResult("INVALID_FORMAT", "Invalid Telegram link format")
	}
	if parsed.Kind == KindSpecial {
		return failResult("NOT_A_CHAT", "Link is not a joinable chat")
	}
	if parsed.Kind == KindPrivatePost {
		return failResult("NOT_IMPLEMENTED", "Private post links are not supported")
	}

	// Cache check
	cacheKey := cacheKeyFor(parsed)
	if cacheKey != "" {
		if cached, ok := ins.cache.Get(cacheKey); ok {
			slog.Debug("cache hit", "key", cacheKey)
			return cached
		}
	}

	var result InspectResult

	switch parsed.Kind {
	case KindBotStart, KindBotStartWithReferral:
		result = ins.inspectBot(ctx, parsed)
	case KindPublicUsername, KindBoostLink:
		result = ins.inspectUsername(ctx, parsed)
	case KindPublicPost:
		result = ins.inspectPost(ctx, parsed)
	case KindPublicPostComment:
		result = ins.inspectComment(ctx, parsed)
	case KindStoryLink:
		result = ins.inspectStory(ctx, parsed)
	case KindInvite:
		result = ins.inspectInvite(ctx, parsed)
	default:
		result = failResult("UNKNOWN_KIND", "Unknown link type: "+string(parsed.Kind))
	}

	if result.Kind == "" {
		result.Kind = string(parsed.Kind)
	}

	if result.OK && cacheKey != "" {
		ins.cache.Set(cacheKey, result)
	}

	return result
}

// ── BOT START ──────────────────────────────────────────────
func (ins *Inspector) inspectBot(ctx context.Context, p ParsedLink) InspectResult {
	// 1) User pool
	if r := ins.tryUserResolve(ctx, p.Username); r != nil {
		if r.ChatType == "channel" || r.ChatType == "supergroup" || r.ChatType == "group" {
			return ins.inspectUsername(ctx, p)
		}
		r.Kind = string(p.Kind)
		// If bot has no member count, try HTML to get monthly users
		if r.MemberCount == 0 {
			if hr := ins.html.Inspect(p.Username); hr != nil && hr.OK && hr.MemberCount > 0 {
				r.MemberCount = hr.MemberCount
			}
		}
		return *r
	}

	// 2) Bot MTProto (optional)
	if ins.bot.IsReady() {
		r, err := ins.bot.ResolveUsername(ctx, p.Username)
		if err == nil && r != nil && r.OK {
			if r.ChatType == "channel" || r.ChatType == "supergroup" || r.ChatType == "group" {
				return ins.inspectUsername(ctx, p)
			}
			r.Kind = string(p.Kind)
			// If bot has no member count, try HTML to get monthly users
			if r.MemberCount == 0 {
				if hr := ins.html.Inspect(p.Username); hr != nil && hr.OK && hr.MemberCount > 0 {
					r.MemberCount = hr.MemberCount
				}
			}
			return *r
		}
		logMtprotoErr("bot", p.Username, err)
	}

	// 3) HTML
	if r := ins.html.Inspect(p.Username); r != nil && r.OK {
		r.Kind = string(p.Kind)
		return *r
	}

	if looksLikeBot(p.Username) {
		return InspectResult{OK: true, Kind: string(p.Kind), ChatType: "bot", Title: p.Username, Source: "fallback", IsBot: true}
	}
	return failResult("RESOLVE_FAILED", fmt.Sprintf("Cannot resolve @%s", p.Username))
}

// ── PUBLIC USERNAME / BOOST ────────────────────────────────
func (ins *Inspector) inspectUsername(ctx context.Context, p ParsedLink) InspectResult {
	// 1) User pool
	if r := ins.tryUserResolve(ctx, p.Username); r != nil {
		if p.Kind == KindBoostLink {
			r.IsBoost = true
		}
		return *r
	}

	// 2) Bot MTProto (optional)
	if ins.bot.IsReady() {
		r, err := ins.bot.ResolveUsername(ctx, p.Username)
		if err == nil && r != nil && r.OK {
			if p.Kind == KindBoostLink {
				r.IsBoost = true
			}
			return *r
		}
		logMtprotoErr("bot", p.Username, err)
	}

	// 3) HTML
	if r := ins.html.Inspect(p.Username); r != nil && r.OK {
		r.Kind = string(p.Kind)
		if p.Kind == KindBoostLink {
			r.IsBoost = true
		}
		return *r
	}

	return failResult("RESOLVE_FAILED", "Chat or user does not exist")
}

// ── PUBLIC POST ────────────────────────────────────────────
func (ins *Inspector) inspectPost(ctx context.Context, p ParsedLink) InspectResult {
	// 1) User pool — resolve + get post
	if r := ins.tryUserResolve(ctx, p.Username); r != nil {
		r.Kind = "public_post"
		r.PostID = p.PostID
		return *r
	}

	// 2) Bot MTProto
	if ins.bot.IsReady() {
		r, err := ins.bot.GetPostInfo(ctx, p.Username, p.PostID)
		if err == nil && r != nil && r.OK {
			return *r
		}
		logMtprotoErr("bot", fmt.Sprintf("%s/%d", p.Username, p.PostID), err)
	}

	// 3) HTML
	link := fmt.Sprintf("https://t.me/%s/%d", p.Username, p.PostID)
	if r := ins.html.Inspect(link); r != nil && r.OK {
		r.PostID = p.PostID
		r.Kind = "public_post"
		return *r
	}

	return failResult("RESOLVE_FAILED", "Post does not exist")
}

// ── COMMENT ────────────────────────────────────────────────
func (ins *Inspector) inspectComment(ctx context.Context, p ParsedLink) InspectResult {
	// 1) User pool
	if r := ins.tryUserResolve(ctx, p.Username); r != nil {
		r.Kind = string(p.Kind)
		r.PostID = p.PostID
		return *r
	}

	// 2) Bot
	if ins.bot.IsReady() {
		r, err := ins.bot.ResolveUsername(ctx, p.Username)
		if err == nil && r != nil && r.OK {
			r.Kind = string(p.Kind)
			r.PostID = p.PostID
			return *r
		}
	}

	return failResult("RESOLVE_FAILED", "Chat or user does not exist")
}

// ── STORY ──────────────────────────────────────────────────
func (ins *Inspector) inspectStory(ctx context.Context, p ParsedLink) InspectResult {
	// 1) User pool — can access stories
	if r := ins.tryUserResolve(ctx, p.Username); r != nil {
		if r.ChatType != "channel" {
			return failResult("NOT_CHANNEL", "Only public channel story links are allowed")
		}
		r.Kind = "story_link"
		return *r
	}

	// 2) Bot
	if ins.bot.IsReady() {
		r, err := ins.bot.ResolveUsername(ctx, p.Username)
		if err == nil && r != nil && r.OK {
			if r.ChatType != "channel" {
				return failResult("NOT_CHANNEL", "Only public channel story links are allowed")
			}
			r.Kind = "story_link"
			return *r
		}
	}

	// 3) HTML
	if r := ins.html.Inspect(p.Username); r != nil && r.OK {
		if r.ChatType != "channel" {
			return failResult("NOT_CHANNEL", "Only public channel story links are allowed")
		}
		r.Kind = "story_link"
		return *r
	}

	return failResult("RESOLVE_FAILED", "Cannot resolve channel for story")
}

// ── INVITE ─────────────────────────────────────────────────
func (ins *Inspector) inspectInvite(ctx context.Context, p ParsedLink) InspectResult {
	hash := p.Hash

	// 1) User pool — best for invite links
	acct := ins.pool.Acquire()
	if acct != nil {
		r, err := acct.CheckInvite(ctx, hash)
		if err == nil && r != nil && r.OK {
			ins.pool.SetCooldown(acct, 2*time.Second)
			ins.pool.Release(acct)
			return *r
		}
		if err != nil {
			if isFloodErr(err) {
				ins.pool.SetFloodWait(acct, extractFloodDuration(err))
			} else {
				ins.pool.SetCooldown(acct, 3*time.Second)
			}
			logMtprotoErr("user", "invite:"+hash, err)
		}
		ins.pool.Release(acct)
	}

	// 2) Bot (often limited for invites)
	if ins.bot.IsReady() {
		r, err := ins.bot.CheckInvite(ctx, hash)
		if err == nil && r != nil && r.OK {
			return *r
		}
		logMtprotoErr("bot", "invite:"+hash, err)
	}

	// 3) HTML
	link := fmt.Sprintf("https://t.me/+%s", hash)
	if r := ins.html.Inspect(link); r != nil && r.OK {
		r.Kind = "invite"
		r.IsInvite = true
		return *r
	}

	return failResult("INVALID_LINK", "Invalid invite link")
}

// ── HELPERS ────────────────────────────────────────────────

func (ins *Inspector) tryUserResolve(ctx context.Context, username string) *InspectResult {
	acct := ins.pool.Acquire()
	if acct == nil {
		return nil
	}

	r, err := acct.ResolveUsername(ctx, username)
	if err != nil {
		if isFloodErr(err) {
			ins.pool.SetFloodWait(acct, extractFloodDuration(err))
		} else {
			ins.pool.SetCooldown(acct, 3*time.Second)
		}
		ins.pool.Release(acct)
		logMtprotoErr("user", username, err)
		return nil
	}
	ins.pool.SetCooldown(acct, 2*time.Second)
	ins.pool.Release(acct)
	return r
}

func failResult(code, msg string) InspectResult {
	return InspectResult{OK: false, ErrorCode: code, ErrorMessage: msg, Source: "go"}
}

func tempResult(code string, err error) InspectResult {
	msg := "Temporary infrastructure issue"
	if err != nil {
		msg = err.Error()
	}
	return InspectResult{OK: false, Temporary: true, ErrorCode: code, ErrorMessage: msg, Source: "go"}
}

func isFloodOrTemp(err error) bool {
	if err == nil {
		return false
	}
	s := err.Error()
	return isFloodErr(err) ||
		strings.Contains(s, "TIMEOUT") ||
		strings.Contains(s, "deadline exceeded") ||
		strings.Contains(s, "connection refused") ||
		strings.Contains(s, "EOF")
}

func isFloodErr(err error) bool {
	if err == nil {
		return false
	}
	return strings.Contains(err.Error(), "FLOOD_WAIT") || strings.Contains(err.Error(), "FLOOD_PREMIUM_WAIT")
}

func extractFloodDuration(err error) time.Duration {
	s := err.Error()
	if idx := strings.Index(s, "FLOOD_WAIT_"); idx >= 0 {
		numStr := s[idx+11:]
		if spIdx := strings.IndexAny(numStr, " ,:;)"); spIdx > 0 {
			numStr = numStr[:spIdx]
		}
		var sec int
		if _, e := fmt.Sscanf(numStr, "%d", &sec); e == nil {
			return time.Duration(sec) * time.Second
		}
	}
	return 60 * time.Second
}

func cacheKeyFor(p ParsedLink) string {
	switch p.Kind {
	case KindPublicUsername, KindBoostLink:
		return "u:" + p.Username
	case KindPublicPost:
		return fmt.Sprintf("p:%s:%d", p.Username, p.PostID)
	case KindBotStart, KindBotStartWithReferral:
		return "b:" + p.Username
	}
	return ""
}

func logMtprotoErr(source, target string, err error) {
	if err != nil {
		slog.Debug("mtproto error", "source", source, "target", target, "error", err.Error())
	}
}
