# Provider API Documentation

**Base URL:** `https://smmtool.org/`

All requests and responses use **JSON** (`Content-Type: application/json`).


## Authentication


| Header | Value |
|--------|-------|
| `X-Provider-Token` | provider secret token |

### 1. Claim Task by Phone

```
POST /api/provider/telegram/getOrder
```

**Request body:**

```json
{
  "account_identity": "+37499111222",
  "api_token": "8fcedaf804894819ed0cffcd1aa4729478eaed1fa52228840cdb1a67e1e866ec"
}
```

| Field | Type | Required | Description                                |
|-------|------|----------|--------------------------------------------|
| `account_identity` | string | Yes | Phone number of the account getting a task |

**Response `200` — task found:**

```json
{

      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "url": "https://t.me/channel"
}
```

**Response `200` — no task available:**

```json
{
    "id": null,
    "url": null
}
```


### 2. Check Task, Ignore Task

```
POST /api/provider/telegram/check
POST /api/provider/telegram/ignore
```

**Request body:**

```json
{
  "order_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "account_identity": "+37499111222",
  "api_token": "8fcedaf804894819ed0cffcd1aa4729478eaed1fa52228840cdb1a67e1e866ec"
}
```


**Response `200` — accepted:**

```json
{ "ok": true }
```

**Response `400` — task not found:**

```json
{ "ok": false, "error": "Task not found" }
```

PROVIDER_TOKEN=8fcedaf804894819ed0cffcd1aa4729478eaed1fa52228840cdb1a67e1e866ec
