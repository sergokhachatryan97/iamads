# Provider API Documentation

**Base URL:** `https://your-domain.com/api`

All requests and responses use **JSON** (`Content-Type: application/json`).

---

## Table of Contents

1. [Authentication](#authentication)
2. [Provider Pull API](#provider-pull-api)
   - [Claim Task by Phone](#3-claim-task-by-phone)
   - [Report Task Result](#4-report-task-result)
4. [Order Status Reference](#order-status-reference)
5. [Error Responses](#error-responses)

---

## Authentication

There are two separate APIs, each using a different token header.

### Provider Pull API

Used by Telegram task execution workers.

| Header | Value |
|--------|-------|
| `X-Provider-Token` | Your provider secret token |



### 3. Claim Task by Phone

Alternative to Pull Tasks. The provider supplies a phone number and the server returns the task assigned to that specific account.

Priority order: **unsubscribe tasks first**, then subscribe tasks.

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

For `invite_subscribers` action (Invite Subscribers From Other Channel), the task includes both links:

```json
{
  "task_id": "...",
  "order_id": 123,
  "action": "invite_subscribers",
  "link": "https://t.me/target_channel",
  "link_2": "https://t.me/source_channel",
  "link_hash": "abc123"
}
```

| Field | Description |
|-------|-------------|
| `link` | Target channel/group (invite TO) |
| `link_2` | Source channel (invite FROM) — only present for invite_subscribers |

**Response `200` — no task available:**

```json
{
  "ok": true,
  "count": 0,
  "tasks": []
}
```

**Rate limits enforced per phone:**

| Limit | Value |
|-------|-------|
| Cooldown between claims | 5 seconds |
| Max subscribe tasks per day | 5 |
| Max unsubscribe tasks per day | 5 |
| Max active subscriptions | 500 |

Task lease TTL: **90 seconds**.

---

### 4. Report Task Result

Report the outcome of a task. Call this after executing each task received from Pull or Claim.

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
| `task_id` | string | Yes | The `task_id` received from pull/claim |
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

> **Note:** Reporting an already-finalized task returns `{"ok": true}` without re-applying the result (idempotent).

---

## Quick Reference

### Provider Pull API (`X-Provider-Token`)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `POST` | `/api/provider/telegram/tasks/claim` | Claim task for specific phone |
| `POST` | `/api/provider/telegram/tasks/report` | Report task result |

