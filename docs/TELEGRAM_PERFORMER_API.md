# Telegram Performer API (Provider → Telegram)

This document describes how a **Telegram performer** (executor) polls for Telegram tasks and reports results using the `provider/telegram` routes.

These endpoints are **not** the public client Provider API (`/api/v2`). They are used by internal/external worker apps that perform Telegram actions (subscribe, view, reaction, bot start, etc.).

---

## Base URL

All routes below are under:

- `GET /api/provider/telegram/...`

(The `/api` prefix comes from Laravel `routes/api.php`.)

---

## Authentication

All requests must pass `auth.provider`.

- **Token location**: send `api_token` either:
  - as a query/body param: `?api_token=...`
  - or as a header: `api_token: ...`
- **Expected token**: `config('services.provider.token')` (usually from env).

If token is missing/invalid:

- **401**

```json
{ "ok": false, "error": "Invalid provider token" }
```

If the server is not configured with a token:

- **500**

```json
{ "ok": false, "error": "Provider authentication not configured" }
```

---

## Poll throttling (`throttle.provider_poll`)

`GET /getOrder` is protected by a Redis-based throttle keyed by `account_identity`.

- Default minimum poll interval is `config('services.provider.poll_interval_seconds', 10)`.
- If the same `account_identity` polls faster than allowed, the API returns an **empty tasks** response immediately (no DB work):

```json
{ "ok": true, "count": 0, "tasks": [] }
```

If Redis is unavailable, throttling fails open and the request proceeds.

---

## 1) Claim a task: `GET /provider/telegram/getOrder`

### Route

- `GET /api/provider/telegram/getOrder`
- Middleware: `auth.provider`, `throttle.provider_poll`

### Query parameters

| Field | Required | Type | Notes |
|---|---:|---|---|
| `api_token` | Yes | string | Provider token (can be header instead). |
| `account_identity` | Yes | string | Performer identity (typically phone). |
| `service_id` | Yes | int | Telegram service ID used to resolve the task scope. |

### Response (task found)

```json
{
  "id": "12345",
  "url": "https://t.me/some_channel",
  "action": "subscribe"
}
```

| Field | Type | Meaning |
|---|---|---|
| `id` | string | Internal Telegram task ID (use for reporting). |
| `url` | string | Target link to act on. |
| `action` | string | Task action. Default is `subscribe` if not provided by the task payload. |

### Response (no work / throttled)

The claim endpoint may return an empty response for **any** of these reasons:

- poll throttling triggered for this `account_identity`
- service queue truly empty (service-wide short cache)
- phone cannot claim right now (phone-specific short cache, cooldown/cap, already-member, etc.)
- DB connection pool exhausted (returns empty instead of error)

Empty response shape:

```json
{ "ok": true, "count": 0, "tasks": [] }
```

### Notes on scope (default vs premium)

Scope is auto-resolved from `service_id` → `services.template_key`.

- Premium templates map to `premium` scope
- Others map to `default` scope

There are also legacy premium routes (see below), but the unified claim behaves the same.

---

## 2) Mark task as done: `GET /provider/telegram/check`

### Route

- `GET /api/provider/telegram/check`
- Middleware: `auth.provider`

### Query parameters

| Field | Required | Type | Notes |
|---|---:|---|---|
| `api_token` | Yes | string | Provider token (can be header instead). |
| `order_id` | Yes | string | **Telegram task ID** returned as `id` from `/getOrder`. |
| `account_identity` | Yes | string | Performer identity (phone). |

### Behavior

This endpoint reports the task result as:

- `state = "done"`
- `ok = true`

### Response

```json
{ "ok": true }
```

If the task cannot be reported (e.g. wrong scope / not found), the API returns:

- **400**

```json
{ "ok": false, "error": "..." }
```

---

## 3) Ignore / fail a task: `GET /provider/telegram/ignore`

### Route

- `GET /api/provider/telegram/ignore`
- Middleware: `auth.provider`

### Query parameters

| Field | Required | Type | Notes |
|---|---:|---|---|
| `api_token` | Yes | string | Provider token (can be header instead). |
| `order_id` | Yes | string | **Telegram task ID** returned as `id` from `/getOrder`. |
| `account_identity` | Yes | string | Performer identity (phone). |
| `error` | No | string | Optional reason (stored in task result). |

### Behavior

This endpoint reports the task result as:

- `state = "failed"`
- `ok = false`
- `error = (optional)`

### Response

```json
{ "ok": true }
```

If the task cannot be reported, the API returns:

- **400**

```json
{ "ok": false, "error": "..." }
```

---

## Premium routes

These exist for backward compatibility and force the **premium** scope for check/ignore.

- `GET /api/provider/telegram/premium/getOrder`
- `GET /api/provider/telegram/premium/check`
- `GET /api/provider/telegram/premium/ignore`

For `/premium/getOrder`, the controller still uses the unified claim logic (scope is auto-detected from `service_id`), so premium routing is mostly relevant for reporting endpoints.

---

## Recommended performer loop (pseudo)

1. Poll for a task:
   - call `/getOrder?account_identity=...&service_id=...`
   - if response is empty (`{ok:true,count:0,tasks:[]}`), wait at least the poll interval and retry
2. Perform the action on `url` based on `action`
3. Report:
   - success → `/check?order_id={id}&account_identity=...`
   - cannot/should not do → `/ignore?order_id={id}&account_identity=...&error=...`

---

## Troubleshooting

- **Getting empty tasks constantly**
  - ensure you wait at least `services.provider.poll_interval_seconds` between polls for the same `account_identity`
  - verify `service_id` is correct for the type of Telegram work you expect
  - your phone may be temporarily gated (cooldown/cap/already-member); wait and retry

- **401 Invalid provider token**
  - confirm `api_token` matches server `services.provider.token`

