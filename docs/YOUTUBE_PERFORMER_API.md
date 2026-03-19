# YouTube Performer API

Documentation for performers (YouTube workers) integrating with the panel. Describes endpoints, request format, and response format.

---

## Base URL

```
https://smmtool.org/api/provider/youtube
```

---

## Authentication

All requests require provider authentication.

| Method | How to send |
|--------|-------------|
| Query param | `?api_token=YOUR_SECRET_TOKEN` | 
`YOUR_SECRET_TOKEN` = `8fcedaf804894819ed0cffcd1aa4729478eaed1fa52228840cdb1a67e1e866ec`
**Example:**
```
GET /api/provider/youtube/getOrder?api_token=xxx&account_identity=+1234567890
```

Without a valid token, the API returns `401`:
```json
{
  "ok": false,
  "error": "Invalid provider token"
}
```

---

## Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/getOrder` | Claim one task |
| GET | `/check` | Mark task as done |
| GET | `/ignore` | Mark task as failed/skipped |
| GET | `/orders-list` | List awaiting orders (optional) |

---

## 1. Get Order (Claim Task)

**Endpoint:** `GET /api/provider/youtube/getOrder`

**Request:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `account_identity` | string | Yes | Performer identifier (e.g. phone number, account ID) |
| `api_token` | string | Yes* | Provider secret (*or send in header) |

**Example:**
```
GET /api/provider/youtube/getOrder?account_identity=+37499111222&api_token=your_token
```

**Response — task found (200):**
```json
{
  "ok": true,
  "count": 1,
  "task_id": "01HXYZ123ABC456DEF",
  "link": "https://www.youtube.com/watch?v=VIDEO_ID",
  "link_hash": "abc123...",
  "action": "view",
  "target": null,
  "order": {
    "id": 123,
    "quantity": 1000,
    "delivered": 0,
    "remains": 1000,
    "target_quantity": 1000,
    "dripfeed_enabled": false,
    "service_description": "YouTube Views",
    "service_name": "High Retention Views",
    "service_id": 5,
    "category": "YouTube"
  },
  "service": { ... }
}
```

**Response — no task available (200):**
```json
{
  "ok": true,
  "count": 0,
  "tasks": [],
  "task_id": null,
  "link": null,
  "order": null
}
```

| Field | Description |
|-------|-------------|
| `task_id` | ULID — use this for `/check` and `/ignore` |
| `link` | YouTube URL to perform action on |
| `action` | `view`, `subscribe`, `comment`, `react`, etc. |
| `target` | For subscribe: channel/video target (nullable) |

---

## 2. Check (Mark Task Done)

**Endpoint:** `GET /api/provider/youtube/check`

Call when the performer **completed the task successfully**.

**Request:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `task_id` | string | Yes | The `task_id` from getOrder |
| `api_token` | string | Yes* | Provider secret (*or header) |

**Example:**
```
GET /api/provider/youtube/check?task_id=01HXYZ123ABC456DEF&api_token=your_token
```

**Response — success (200):**
```json
{
  "ok": true
}
```

**Response — error (400):**
```json
{
  "ok": false,
  "error": "Failed to check task"
}
```

---

## 3. Ignore (Mark Task Failed)

**Endpoint:** `GET /api/provider/youtube/ignore`

Call when the performer **could not complete** or **skipped** the task.

**Request:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `task_id` | string | Yes | The `task_id` from getOrder |
| `api_token` | string | Yes* | Provider secret (*or header) |

**Example:**
```
GET /api/provider/youtube/ignore?task_id=01HXYZ123ABC456DEF&api_token=your_token
```

**Response — success (200):**
```json
{
  "ok": true
}
```

**Response — error (400):**
```json
{
  "ok": false,
  "error": "Failed to ignore task"
}
```

---

## 4. Orders List (Awaiting Orders)

**Endpoint:** `GET /api/provider/youtube/orders-list`

Returns all awaiting YouTube orders. Optional — for informational or pre-fetch use.

**Request:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `api_token` | string | Yes* | Provider secret (*or header) |

**Example:**
```
GET /api/provider/youtube/orders-list?api_token=your_token
```

**Response (200):**
```json
{
  "ok": true,
  "count": 2,
  "orders": [
    {
      "id": 123,
      "link": "https://www.youtube.com/watch?v=...",
      "quantity": 1000,
      "delivered": 0,
      "remains": 1000,
      "status": "awaiting",
      "service_id": 5,
      "service_name": "YouTube Views",
      "service_description": "...",
      "category_id": 2,
      "category_name": "YouTube",
      "created_at": "2026-03-18T12:00:00.000000Z"
    }
  ]
}
```

---

## Typical Workflow

1. **Claim:** `GET /getOrder?account_identity=+PHONE&api_token=TOKEN`
2. If `count === 0` → no task, retry later
3. If `count === 1` → use `task_id` and `link`, perform the action (e.g. view video)
4. **Report success:** `GET /check?task_id=TASK_ID&api_token=TOKEN`
5. **Or report failure:** `GET /ignore?task_id=TASK_ID&api_token=TOKEN`

---

## Task Lease

- Each claimed task is **leased** for ~3 minutes
- If not reported in time, the task is recycled (another performer can claim it)
- Always call `/check` or `/ignore` when done

---
