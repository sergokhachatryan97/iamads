# Provider API Documentation

This API provides a single-endpoint integration style for reseller panels, automation tools, and third-party platforms. All requests use the same URL with different `action` values.

---

## Endpoint

```
POST https://smmtool.org/api/v2
Content-Type: application/json
```

All requests must be `POST` with a JSON body. The `Content-Type: application/json` header is required.

---

## Authentication

Include your API key in every request body as the `key` field. The same API key you use for the REST API works for the Provider API.

1. Enable API access in your account at **API Access** in the dashboard.
2. Copy your API key (use **Show** to reveal it).
3. Include it in every request: `"key": "your_api_key"`

**Important:** Your account must have API enabled and must not be suspended.

---

## Supported Actions

### 1. services – List Available Services

Returns all active services available for ordering. Services are listed with IDs, names, rates, and quantity limits. Client-specific pricing is applied.

**Request:**
```json
{
  "key": "your_api_key",
  "action": "services"
}
```

**Response:**
```json
[
  {
    "service": 1,
    "name": "YouTube Views",
    "type": "Default",
    "category": "YouTube",
    "rate": "1.2000",
    "min": "100",
    "max": "10000",
    "dripfeed": false,
    "refill": false,
    "cancel": true
  },
  {
    "service": 2,
    "name": "Telegram Members",
    "type": "Default",
    "category": "Telegram",
    "rate": "2.5000",
    "min": "50",
    "max": "5000",
    "dripfeed": true,
    "refill": false,
    "cancel": false
  }
]
```

| Field    | Type   | Description                          |
|----------|--------|--------------------------------------|
| service  | int    | Service ID (use this in the add action) |
| name     | string | Service name                         |
| type     | string | Always `"Default"`                   |
| category | string | Category name                        |
| rate     | string | Price per 1000 units in USD          |
| min      | string | Minimum quantity                     |
| max      | string | Maximum quantity (`"0"` = no limit)  |
| dripfeed | bool   | Whether drip-feed is supported       |
| refill   | bool   | Whether refill is supported          |
| cancel   | bool   | Whether cancel is supported          |

---

### 2. add – Create Order

Creates a new order. Use the service ID from the `services` action.

**Request:**
```json
{
  "key": "your_api_key",
  "action": "add",
  "service": 123,
  "link": "https://example.com/your-content",
  "quantity": 1000,
  "speed_tier": "normal",
  "order": "YOUR-EXTERNAL-ID-123"
}
```

| Field      | Required | Type   | Description |
|------------|----------|--------|-------------|
| service    | Yes      | int    | Service ID from the services list |
| link       | Yes      | string | Target URL (e.g. YouTube video, Telegram channel). If you omit `http://` or `https://`, it will be prepended automatically. Max 2048 characters. |
| quantity   | Yes      | int    | Order quantity. Must be between the service min and max. |
| speed_tier | No       | string | Delivery speed. Use `normal`, `fast`, or `super_fast` if the service supports it. Default: `normal`. Max 50 characters. |
| order      | No       | string | Your external order ID for tracking and idempotency. If omitted, an ID is auto-generated. If you send the same `order` value again, the existing order is returned (no duplicate charge). Max 255 characters. |

**Response:**
```json
{
  "order": 123456
}
```

| Field  | Type | Description |
|--------|------|-------------|
| order  | int  | Internal order ID. Use this or your external `order` value when checking status. |

---

### 3. status – Check Order Status

Returns the current status and details of an order.

**Request:**
```json
{
  "key": "your_api_key",
  "action": "status",
  "order": 555
}
```

| Field | Required | Type        | Description |
|-------|----------|-------------|-------------|
| order | Yes      | int or string | Internal order ID (number) **or** your external order ID (string) that you passed in the `add` action. |

**Response:**
```json
{
  "charge": "1.20",
  "start_count": "5000",
  "status": "Completed",
  "remains": "0"
}
```

| Field       | Type   | Description |
|-------------|--------|-------------|
| charge      | string | Amount charged in USD (2 decimal places) |
| start_count | string | Starting count at order creation (e.g. existing views) |
| status      | string | Current order status (see below) |
| remains     | string | Remaining quantity to deliver (`"0"` when done) |

**Possible `status` values**

| `status` | Meaning |
|----------|---------|
| `Pending` | Order is queued and waiting to start. |
| `Processing` | Order is being validated (link checks). |
| `In progress` | Order is actively being delivered. |
| `Completed` | Order is fully delivered (`remains` is `”0”`). |
| `Canceled` | Order was canceled before full delivery. |
| `Failed` | Order failed (invalid link or other error). |

**Note:** Capitalization matters — `In progress` has a space, `Canceled` has one “l”.

---

### 4. balance – Check Balance

Returns your current account balance.

**Request:**
```json
{
  "key": "your_api_key",
  "action": "balance"
}
```

**Response:**
```json
{
  "balance": "100.50",
  "currency": "USD"
}
```

| Field    | Type   | Description |
|----------|--------|-------------|
| balance  | string | Available balance (2 decimal places) |
| currency | string | Always `"USD"` |

---

## Error Responses

All errors return JSON with an `error` field:

```json
{
  "error": "Error message here"
}
```

| HTTP Status | Description | Example |
|-------------|-------------|---------|
| 400         | Bad request | `{"error": "Missing action"}` – No `action` in body |
| 400         | Bad request | `{"error": "Unknown action"}` – Invalid action name |
| 401         | Unauthorized | `{"error": "Missing or invalid API key"}` – Wrong key, empty key, or API not enabled |
| 403         | Forbidden | `{"error": "Account is suspended"}` |
| 404         | Not found | `{"error": "Order not found"}` – Order does not exist or belongs to another account |
| 422         | Validation error | `{"error": "Insufficient balance. Please top up."}` |
| 422         | Validation error | `{"error": "Service not found or inactive."}` |
| 422         | Validation error | `{"error": "The link field is required."}` – Or other field validation messages |
| 500         | Server error | `{"error": "An error occurred. Please try again."}` |

---

## Example cURL Commands

**List services:**
```bash
curl -X POST https://smmtool.org/api/v2 \
  -H "Content-Type: application/json" \
  -d '{"key":"your_api_key","action":"services"}'
```

**Create order:**
```bash
curl -X POST https://smmtool.org/api/v2 \
  -H "Content-Type: application/json" \
  -d '{"key":"your_api_key","action":"add","service":12,"link":"https://youtube.com/watch?v=xxx","quantity":500,"order":"MY-ORDER-001"}'
```

**Check status (by internal ID):**
```bash
curl -X POST https://smmtool.org/api/v2 \
  -H "Content-Type: application/json" \
  -d '{"key":"your_api_key","action":"status","order":123456}'
```

**Check status (by external ID):**
```bash
curl -X POST https://smmtool.org/api/v2 \
  -H "Content-Type: application/json" \
  -d '{"key":"your_api_key","action":"status","order":"MY-ORDER-001"}'
```

**Check balance:**
```bash
curl -X POST https://smmtool.org/api/v2 \
  -H "Content-Type: application/json" \
  -d '{"key":"your_api_key","action":"balance"}'
```

---

## Integration Notes

- **Idempotency:** When creating orders, pass your own `order` value to avoid duplicates. Sending the same `order` twice returns the existing order without creating a new one.
- **Order lookup:** For the `status` action, you can use either the internal order ID (returned by `add`) or your external `order` value.
- **Link format:** Links without `http://` or `https://` are automatically prefixed with `https://`.
- **Balance:** Balance is shared between web and API orders. Ensure sufficient balance before creating orders.
