# Provider API Documentation

**Base URL:** `https://smmtool.org/`

All requests and responses use **JSON** (`Content-Type: application/json`).


## Authentication


| Header | Value |
|--------|-------|
| `X-Provider-Token` | provider secret token |

### 1. Claim Task by Phone

```
POST /api/provider/telegram/tasks/claim
```

**Request body:**

```json
{
  "phone": "+37499111222"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `phone` | string | Yes | Phone number of the account claiming a task |

**Response `200` — task found:**

```json
{
  "ok": true,
  "count": 1,
  "tasks": [
    {
      "task_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "order_id": 123,
      "action": "subscribe",
      "link": "https://t.me/channel",
      "link_hash": "abc123"
    }
  ]
}
```

**Response `200` — no task available:**

```json
{
  "ok": true,
  "count": 0,
  "tasks": []
}
```


### 2. Report Task Result

Report the outcome of a task. Call this after executing each task received from  Claim.

```
POST /api/provider/telegram/tasks/report
```

**Request body:**

```json
{
  "task_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "state": "done",
  "ok": true,
  "error": null,
  "retry_after": null,
  "provider_task_id": null,
  "data": null
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `task_id` | string | Yes | The `task_id` received from claim |
| `state` | string | Yes | `"done"`, `"pending"`, or `"failed"` |
| `ok` | boolean | No | Whether the action succeeded (default: `false`) |
| `error` | string\|null | No | Error message if the task failed |
| `retry_after` | integer\|null | No | Seconds until retry (used with `state: "pending"`, clamped 60–300) |
| `provider_task_id` | string\|null | No | Your internal task ID (for reference) |
| `data` | object\|null | No | Any additional result data |

**`state` values explained:**

| `state` | `ok` | Meaning |
|---------|------|---------|
| `"done"` | `true` | Task completed successfully. Order counters are updated. |
| `"done"` | `false` | Task failed. Order is reset to `pending` with the error message. |
| `"pending"` | — | Task is still in progress. Lease is extended by `retry_after` seconds. |
| `"failed"` | — | Task failed permanently. |

**Response `200` — accepted:**

```json
{ "ok": true }
```

**Response `400` — task not found:**

```json
{ "ok": false, "error": "Task not found" }
```

PROVIDER_TOKEN=8fcedaf804894819ed0cffcd1aa4729478eaed1fa52228840cdb1a67e1e866ec
