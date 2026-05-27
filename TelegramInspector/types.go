package main

// InspectRequest from Laravel.
type InspectRequest struct {
	Link               string   `json:"link"`
	ServiceTemplateKey []string `json:"service_template_key,omitempty"`
	BotToken           string   `json:"bot_token,omitempty"`
	ForB2C             bool     `json:"for_b2c,omitempty"`
}

// InspectResult returned to Laravel — compatible with PHP TelegramInspector format.
type InspectResult struct {
	OK              bool   `json:"ok"`
	Kind            string `json:"kind"`
	ChatType        string `json:"chat_type"`
	Username        string `json:"username,omitempty"`
	Title           string `json:"title,omitempty"`
	MemberCount     int    `json:"member_count"`
	PostID          int    `json:"post_id,omitempty"`
	IsBot           bool   `json:"is_bot,omitempty"`
	IsPrivate       bool   `json:"is_private,omitempty"`
	IsInvite        bool   `json:"is_invite,omitempty"`
	IsPaidJoin      bool   `json:"is_paid_join,omitempty"`
	IsPaidMessages  bool   `json:"is_paid_messages,omitempty"`
	IsPoll          bool   `json:"is_poll,omitempty"`
	IsChannel       bool   `json:"is_channel,omitempty"`
	IsGroup         bool   `json:"is_group,omitempty"`
	IsBoost         bool   `json:"is_boost,omitempty"`
	AudienceType    string `json:"audience_type,omitempty"`
	Temporary       bool   `json:"temporary,omitempty"`
	ErrorCode       string `json:"error_code,omitempty"`
	ErrorMessage    string `json:"error_message,omitempty"`
	Source          string `json:"source"`
	Views           int    `json:"views,omitempty"`
}

// SessionFile represents a Telegram user session JSON file.
type SessionFile struct {
	Phone        string `json:"phone"`
	ApiID        int    `json:"api_id"`
	ApiHash      string `json:"api_hash"`
	TwoFA        string `json:"two_fa"`
	FirstName    string `json:"first_name"`
	LastName     string `json:"last_name"`
	DeviceModel  string `json:"device_model"`
	AppVersion   string `json:"app_version"`
	SystemVer    string `json:"system_version"`
	LangCode     string `json:"lang_code"`
	LangPack     string `json:"lang_pack"`
	SysLangCode  string `json:"system_lang_code"`
	ID           int64  `json:"id"`
	Premium      bool   `json:"premium"`
	MtpData      string `json:"mtp_data"`
}
