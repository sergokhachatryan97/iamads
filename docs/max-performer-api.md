# Max Performer API Documentation

Base URL: `/api/provider/max`

## Authentication

All endpoints require a provider token passed as:
- Query parameter: `?api_token=YOUR_TOKEN`
- Header: `api_token: YOUR_TOKEN`

Returns `401` with `{"ok": false, "error": "Invalid provider token"}` on invalid/missing token.

---

## Endpoints

### 1. Get Order (Claim Task)

Claim a task for a Max account.

```
GET /api/provider/max/getOrder
```

**Parameters:**

| Name               | Type    | Required | Description                        |
|--------------------|---------|----------|------------------------------------|
| `api_token`        | string  | yes      | Provider authentication token      |
| `account_identity` | string  | yes      | Unique identifier of the account   |
| `service_id`       | integer | yes      | Service ID to claim tasks for      |

**Success Response (task found):**

```json
{
    "id": "01JQXYZ...",
    "url": "https://example.com/channel",
    "action": "subscribe",
    "comment_text": "optional comment or null"
}
```

**Success Response (no tasks available):**

```json
{
    "ok": true,
    "count": 0,
    "tasks": []
}
```


### 2. Check (Report Task Done)

Report that a task was completed successfully.

```
GET /api/provider/max/check
```

**Parameters:**

| Name               | Type   | Required | Description                          |
|--------------------|--------|----------|--------------------------------------|
| `api_token`        | string | yes      | Provider authentication token        |
| `order_id`         | string | yes      | Task ID returned from `getOrder`     |
| `account_identity` | string | yes      | Account identity that performed task |

> **Note:** Despite the parameter name `order_id`, this should be the **task ID** (`id` field) returned by `getOrder`.

**Success Response:**

```json
{
    "ok": true
}
```

**Error Response:**

```json
{
    "ok": false,
    "error": "Task not found"
}
```


### 3. Ignore (Skip Task)

Report that a task was ignored/skipped by the performer.

```
GET /api/provider/max/ignore
```

**Parameters:**

| Name               | Type   | Required | Description                          |
|--------------------|--------|----------|--------------------------------------|
| `api_token`        | string | yes      | Provider authentication token        |
| `order_id`         | string | yes      | Task ID returned from `getOrder`     |
| `account_identity` | string | yes      | Account identity that skipped task   |


**Success Response:**

```json
{
    "ok": true
}
```

**Error Response:**

```json
{
    "ok": false,
    "error": "Task not found"
}
```


## Task Lifecycle

```
getOrder (leased) --> check (done)    --> order delivery updated
                  \-> ignore (failed) --> order rollback
                  \-> expired (failed) --> auto-cleanup after 5 min
```

Tasks stuck in `leased` status for more than 5 minutes are automatically marked as `failed` by a background cleanup job.

---


