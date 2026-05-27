package main

import (
	"fmt"
	"io"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"
)

type HtmlInspector struct {
	client *http.Client
}

func NewHtmlInspector() *HtmlInspector {
	return &HtmlInspector{
		client: &http.Client{Timeout: 10 * time.Second},
	}
}

var (
	reOgTitle       = regexp.MustCompile(`<meta\s+property="og:title"\s+content="([^"]*)"`)
	reOgDescription = regexp.MustCompile(`<meta\s+property="og:description"\s+content="([^"]*)"`)
	reMembersExtra  = regexp.MustCompile(`<div class="tgme_page_extra">([^<]*)</div>`)
	reViews         = regexp.MustCompile(`<span class="tgme_widget_message_views">([^<]+)</span>`)
	rePollHTML      = regexp.MustCompile(`tgme_widget_message_poll`)
)

func (h *HtmlInspector) Inspect(link string) *InspectResult {
	tmeURL := toTmeURL(link)
	if tmeURL == "" {
		return nil
	}

	body, err := h.fetch(tmeURL)
	if err != nil {
		return nil
	}

	return h.parse(tmeURL, body)
}

func (h *HtmlInspector) fetch(url string) (string, error) {
	req, _ := http.NewRequest("GET", url, nil)
	req.Header.Set("User-Agent", "Mozilla/5.0 TelegramInspector/1.0")
	req.Header.Set("Accept-Language", "en-US,en;q=0.9")
	resp, err := h.client.Do(req)
	if err != nil {
			return "", err
	}
	defer resp.Body.Close()
	b, err := io.ReadAll(resp.Body)
	return string(b), err
}

func (h *HtmlInspector) parse(url, html string) *InspectResult {
	r := &InspectResult{Source: "html"}

	title := ""
	if m := reOgTitle.FindStringSubmatch(html); m != nil {
		title = m[1]
	}

	extra := ""
	for _, m := range reMembersExtra.FindAllStringSubmatch(html, -1) {
		candidate := strings.TrimSpace(m[1])
		cl := strings.ToLower(candidate)
		if strings.Contains(cl, "subscriber") || strings.Contains(cl, "member") || strings.Contains(cl, "monthly user") {
			extra = candidate
			break
		}
	}

	lower := strings.ToLower(extra)

	if strings.Contains(lower, "subscriber") {
		r.OK = true
		r.ChatType = "channel"
		r.AudienceType = "subscribers"
		r.IsChannel = true
		r.Title = title
		r.MemberCount = parseHumanCount(extra)
		return r
	}
	if strings.Contains(lower, "member") {
		r.OK = true
		r.ChatType = "group"
		r.AudienceType = "members"
		r.IsGroup = true
		r.Title = title
		r.MemberCount = parseHumanCount(extra)
		return r
	}
	if strings.Contains(lower, "monthly user") {
		r.OK = true
		r.ChatType = "bot"
		r.Kind = "bot_start"
		r.IsBot = true
		r.Title = title
		r.MemberCount = parseHumanCount(extra)
		return r
	}

	// Post page
	if strings.Contains(html, "tgme_widget_message") {
		r.OK = true
		r.ChatType = "channel"
		r.IsChannel = true
		r.Title = title
		r.Kind = "public_post"

		// Try embed for views
		embedURL := url
		if strings.Contains(embedURL, "?") {
			embedURL += "&embed=1&mode=tme"
		} else {
			embedURL += "?embed=1&mode=tme"
		}
		if embedBody, err := h.fetch(embedURL); err == nil {
			if m := reViews.FindStringSubmatch(embedBody); m != nil {
				r.Views = parseHumanCount(m[1])
			}
			if rePollHTML.MatchString(embedBody) {
				r.IsPoll = true
			}
		}
		return r
	}

	return nil
}

func parseHumanCount(s string) int {
	s = strings.TrimSpace(s)
	s = strings.ReplaceAll(s, "\u00a0", " ")
	up := strings.ToUpper(s)

	if strings.HasSuffix(up, "K") {
		if f, err := strconv.ParseFloat(strings.TrimSpace(strings.TrimSuffix(up, "K")), 64); err == nil {
			return int(f * 1000)
		}
	}
	if strings.HasSuffix(up, "M") {
		if f, err := strconv.ParseFloat(strings.TrimSpace(strings.TrimSuffix(up, "M")), 64); err == nil {
			return int(f * 1000000)
		}
	}

	cleaned := strings.Map(func(r rune) rune {
		if r >= '0' && r <= '9' {
			return r
		}
		return -1
	}, s)
	if cleaned == "" {
		return 0
	}
	v, _ := strconv.Atoi(cleaned)
	return v
}

func toTmeURL(input string) string {
	input = strings.TrimSpace(input)
	if strings.HasPrefix(input, "https://t.me/") || strings.HasPrefix(input, "http://t.me/") {
		return input
	}
	if strings.HasPrefix(input, "https://telegram.me/") {
		return input
	}
	if matched, _ := regexp.MatchString(`^@[A-Za-z0-9_]{3,32}$`, input); matched {
		return fmt.Sprintf("https://t.me/%s", strings.TrimPrefix(input, "@"))
	}
	if matched, _ := regexp.MatchString(`^[A-Za-z0-9_]{3,32}$`, input); matched {
		return fmt.Sprintf("https://t.me/%s", input)
	}
	if strings.HasPrefix(input, "t.me/") {
		return "https://" + input
	}
	return ""
}
