package main

import (
	"net/url"
	"regexp"
	"strconv"
	"strings"
)

type LinkKind string

const (
	KindUnknown              LinkKind = "unknown"
	KindPublicUsername       LinkKind = "public_username"
	KindPublicPost           LinkKind = "public_post"
	KindPublicPostComment    LinkKind = "public_post_comment_reaction"
	KindBotStart             LinkKind = "bot_start"
	KindBotStartWithReferral LinkKind = "bot_start_with_referral"
	KindInvite               LinkKind = "invite"
	KindStoryLink            LinkKind = "story_link"
	KindBoostLink            LinkKind = "boost_link"
	KindPrivatePost          LinkKind = "private_post"
	KindSpecial              LinkKind = "special"
)

type ParsedLink struct {
	Kind       LinkKind `json:"kind"`
	Raw        string   `json:"raw"`
	Username   string   `json:"username,omitempty"`
	PostID     int      `json:"post_id,omitempty"`
	CommentID  int      `json:"comment_id,omitempty"`
	StoryID    int      `json:"story_id,omitempty"`
	Hash       string   `json:"hash,omitempty"`
	InternalID int      `json:"internal_id,omitempty"`
	Start      string   `json:"start,omitempty"`
	StartKey   string   `json:"start_key,omitempty"`
	HasRef     bool     `json:"has_referrer,omitempty"`
}

var (
	reZeroWidth     = regexp.MustCompile(`[\x{200B}-\x{200D}\x{FEFF}]`)
	reHTTPAt        = regexp.MustCompile(`(?i)^https?://@([a-zA-Z0-9_]{3,32})/?$`)
	reWWWTme        = regexp.MustCompile(`(?i)^www\.(t\.me|telegram\.me)/(.+)$`)
	reSlashAt       = regexp.MustCompile(`^/@([a-zA-Z0-9_]{3,32})$`)
	reAtSlash       = regexp.MustCompile(`^@([a-zA-Z0-9_]{3,32})/$`)
	reUsernameSlash = regexp.MustCompile(`^([a-zA-Z0-9_]{3,32})/$`)
	reDoubleSlash   = regexp.MustCompile(`(?i)^//(t\.me|telegram\.me)/(.+)$`)
	reSingleQS      = regexp.MustCompile(`(?i)^(https?://(t\.me|telegram\.me)/[a-zA-Z0-9_]+/\d+)\?single$`)
	reTmeNoSchema   = regexp.MustCompile(`(?i)^(t\.me/|telegram\.me/)(.+)$`)
	reTmeURL        = regexp.MustCompile(`(?i)^https?://(t\.me|telegram\.me)/(.+)$`)
	reRawUsername   = regexp.MustCompile(`^@?([a-zA-Z0-9_]{3,32})$`)
	reDigitsOnly    = regexp.MustCompile(`^\d+$`)
	reBoost         = regexp.MustCompile(`^boost/([a-zA-Z0-9_]{3,32})$`)
	rePrivate       = regexp.MustCompile(`^c/(\d+)/(\d+)$`)
	reInviteNew     = regexp.MustCompile(`^\+([a-zA-Z0-9_-]+)$`)
	reInviteOld     = regexp.MustCompile(`^joinchat/([a-zA-Z0-9_-]+)$`)
	reStory         = regexp.MustCompile(`^([a-zA-Z0-9_]{3,32})/s/(\d+)$`)
	rePost          = regexp.MustCompile(`^([a-zA-Z0-9_]{3,32})/(\d+)$`)
	reUserPath      = regexp.MustCompile(`^([a-zA-Z0-9_]{3,32})$`)
	reHashFrag      = regexp.MustCompile(`#.*`)
)

func ParseLink(raw string) ParsedLink {
	raw = strings.TrimSpace(raw)
	orig := raw
	if raw == "" {
		return ParsedLink{Kind: KindUnknown, Raw: orig}
	}
	raw = normalizeInput(raw)
	if reDigitsOnly.MatchString(raw) {
		return ParsedLink{Kind: KindUnknown, Raw: orig}
	}
	if m := reTmeNoSchema.FindStringSubmatch(raw); m != nil {
		raw = "https://t.me/" + strings.TrimLeft(m[2], "/")
	}
	if strings.HasPrefix(strings.ToLower(raw), "tg://") {
		return parseTgProtocol(raw, orig)
	}
	if m := reTmeURL.FindStringSubmatch(raw); m != nil {
		pq := m[2]
		parts := strings.SplitN(pq, "?", 2)
		path := reHashFrag.ReplaceAllString(parts[0], "")
		qs := ""
		if len(parts) > 1 {
			qs = parts[1]
		}
		q, _ := url.ParseQuery(qs)
		return parseTMePath(path, q, orig)
	}
	if m := reRawUsername.FindStringSubmatch(raw); m != nil {
		u := strings.ToLower(m[1])
		if looksLikeBot(u) {
			return ParsedLink{Kind: KindBotStart, Raw: orig, Username: u}
		}
		return ParsedLink{Kind: KindPublicUsername, Raw: orig, Username: u}
	}
	return ParsedLink{Kind: KindUnknown, Raw: orig}
}

func normalizeInput(raw string) string {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return raw
	}
	raw = reZeroWidth.ReplaceAllString(raw, "")
	raw = strings.TrimSpace(raw)
	raw = strings.ReplaceAll(raw, "&#64;", "@")
	if m := reHTTPAt.FindStringSubmatch(raw); m != nil {
		return "@" + strings.ToLower(m[1])
	}
	if m := reWWWTme.FindStringSubmatch(raw); m != nil {
		return "https://" + strings.ToLower(m[1]) + "/" + strings.TrimLeft(m[2], "/")
	}
	if m := reSlashAt.FindStringSubmatch(raw); m != nil {
		return "@" + strings.ToLower(m[1])
	}
	if m := reAtSlash.FindStringSubmatch(raw); m != nil {
		return "@" + strings.ToLower(m[1])
	}
	if m := reUsernameSlash.FindStringSubmatch(raw); m != nil {
		return strings.ToLower(m[1])
	}
	if m := reDoubleSlash.FindStringSubmatch(raw); m != nil {
		return "https://" + strings.ToLower(m[1]) + "/" + strings.TrimLeft(m[2], "/")
	}
	raw = reSingleQS.ReplaceAllString(raw, "$1")
	return raw
}

func parseTgProtocol(raw, orig string) ParsedLink {
	u, err := url.Parse(raw)
	if err != nil {
		return ParsedLink{Kind: KindUnknown, Raw: orig}
	}
	host := u.Host
	q := u.Query()
	if host == "resolve" && q.Get("domain") != "" {
		un := strings.ToLower(q.Get("domain"))
		if sid := q.Get("story"); sid != "" {
			if id, e := strconv.Atoi(sid); e == nil && id > 0 {
				return ParsedLink{Kind: KindStoryLink, Raw: orig, Username: un, StoryID: id}
			}
		}
		if key := firstStartKey(q); key != "" {
			val := q.Get(key)
			k := KindBotStart
			if val != "" {
				k = KindBotStartWithReferral
			}
			return ParsedLink{Kind: k, Raw: orig, Username: un, Start: val, StartKey: key, HasRef: val != ""}
		}
		if looksLikeBot(un) {
			return ParsedLink{Kind: KindBotStart, Raw: orig, Username: un}
		}
		return ParsedLink{Kind: KindPublicUsername, Raw: orig, Username: un}
	}
	if host == "join" && q.Get("invite") != "" {
		return ParsedLink{Kind: KindInvite, Raw: orig, Hash: q.Get("invite")}
	}
	return ParsedLink{Kind: KindUnknown, Raw: orig}
}

func parseTMePath(path string, q url.Values, raw string) ParsedLink {
	path = strings.Trim(path, "/")
	if m := reBoost.FindStringSubmatch(path); m != nil {
		return ParsedLink{Kind: KindBoostLink, Raw: raw, Username: strings.ToLower(m[1])}
	}
	if m := rePrivate.FindStringSubmatch(path); m != nil {
		iid, _ := strconv.Atoi(m[1])
		pid, _ := strconv.Atoi(m[2])
		return ParsedLink{Kind: KindPrivatePost, Raw: raw, InternalID: iid, PostID: pid}
	}
	if m := reInviteNew.FindStringSubmatch(path); m != nil {
		return ParsedLink{Kind: KindInvite, Raw: raw, Hash: m[1]}
	}
	if m := reInviteOld.FindStringSubmatch(path); m != nil {
		return ParsedLink{Kind: KindInvite, Raw: raw, Hash: m[1]}
	}
	if m := reStory.FindStringSubmatch(path); m != nil {
		sid, _ := strconv.Atoi(m[2])
		return ParsedLink{Kind: KindStoryLink, Raw: raw, Username: strings.ToLower(m[1]), StoryID: sid}
	}
	if m := rePost.FindStringSubmatch(path); m != nil {
		un := strings.ToLower(m[1])
		pid, _ := strconv.Atoi(m[2])
		if cid := q.Get("comment"); cid != "" {
			if c, e := strconv.Atoi(cid); e == nil && c > 0 {
				return ParsedLink{Kind: KindPublicPostComment, Raw: raw, Username: un, PostID: pid, CommentID: c}
			}
		}
		return ParsedLink{Kind: KindPublicPost, Raw: raw, Username: un, PostID: pid}
	}
	if m := reUserPath.FindStringSubmatch(path); m != nil {
		un := strings.ToLower(m[1])
		if key := firstStartKey(q); key != "" {
			val := q.Get(key)
			k := KindBotStart
			if val != "" {
				k = KindBotStartWithReferral
			}
			return ParsedLink{Kind: k, Raw: raw, Username: un, Start: val, StartKey: key, HasRef: val != ""}
		}
		if looksLikeBot(un) {
			return ParsedLink{Kind: KindBotStart, Raw: raw, Username: un}
		}
		return ParsedLink{Kind: KindPublicUsername, Raw: raw, Username: un}
	}
	return ParsedLink{Kind: KindUnknown, Raw: raw}
}

func firstStartKey(q url.Values) string {
	for _, k := range []string{"start", "startgroup", "startapp", "startattach"} {
		if q.Has(k) {
			return k
		}
	}
	return ""
}

func looksLikeBot(u string) bool {
	return strings.HasSuffix(u, "_bot") || strings.HasSuffix(u, "bot")
}
