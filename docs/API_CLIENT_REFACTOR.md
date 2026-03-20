# API Client Refactor – Summary

API ordering is now a first-class feature of the existing client account system. The same client can place orders from the web panel and via API using an API key.

## What Changed

### 1. Database Migrations

- **`2026_03_20_000001_add_api_fields_to_clients_table.php`**
  - `api_enabled` (boolean, default false)
  - `api_key` (nullable string, unique)
  - `api_key_generated_at` (nullable timestamp)
  - `api_last_used_at` (nullable timestamp)

- **`2026_03_20_000002_add_source_and_external_order_id_to_orders_table.php`**
  - `source` (string, default 'web') – values: `web`, `api`
  - `external_order_id` (nullable string)
  - Unique index on `(client_id, external_order_id)` for idempotency

### 2. API Authentication

- **`AuthenticateApiClient` middleware** – Replaces `AuthenticateExternalClient` for the external API
  - Reads `Authorization: Bearer <api_key>`
  - Resolves client by `api_key` where `api_enabled=true`
  - Sets `api_client` on the request
  - Updates `api_last_used_at` on each request

### 3. Routes (`routes/api.php`)

- External API now uses `auth.api_client` middleware
- Endpoints:
  - `GET /api/external/services` – List services for the authenticated client
  - `POST /api/external/orders` – Create order (idempotent by `external_order_id`)
  - `GET /api/external/orders` – List orders (limit, cursor, status, external_order_id)
  - `POST /api/external/orders/statuses` – Batch status check
  - `GET /api/external/orders/{external_order_id}` – Get single order

### 4. Controllers & Services

- **`ExternalOrderController`** – Refactored
  - Uses `api_client` from request (no config-based default)
  - Uses `OrderService::createApiOrder()` for creation
  - Idempotency by `(client_id, external_order_id)`
  - Status mapping: validating, awaiting, in_progress, completed, canceled, failed

- **`ExternalServiceController`** – New
  - Returns active services with pricing for the authenticated client
  - Response: `service` (id), `name`, `type`, `category`, `rate`, `min`, `max`, `refill`, `cancel`

- **`OrderService::createApiOrder()`** – New
  - Single order creation, balance deduction, inspection dispatch
  - Uses `OrderInspectionDispatcher` (category-aware: Telegram, YouTube, etc.)

### 5. Request Validation

- **`ExternalOrderStoreRequest`** – Updated
  - `service` (required) – maps to service ID
  - `external_order_id`, `link`, `quantity`, optional `speed_tier`, `meta`

### 6. Client Panel

- **API page** (`/api`) – New
  - Enable/disable API access
  - Masked API key with Reveal (fetched via AJAX)
  - Regenerate key
  - Base URL and quick-start examples

- **Navigation** – Added "API" link in client nav

- **Orders page** – Added
  - Source filter (All, Web, API)
  - Source column with Web/API badge

### 7. Models

- **Client** – `api_enabled`, `api_key`, `api_key_generated_at`, `api_last_used_at` (hidden: `api_key`)
- **Order** – `SOURCE_WEB`, `SOURCE_API`, `source`, `external_order_id`

### 8. Inspection

- **OrderInspectionDispatcher** – Already category-aware (Telegram, YouTube)
- Used by `createApiOrder` for link validation

## Deprecated

- `config('services.external_clients.default_client_id')` – No longer used
- `auth.external_client` middleware – Replaced by `auth.api_client`
- `X-Client-Token` header – Replaced by `Authorization: Bearer <api_key>`

## Usage

1. Client enables API in the panel at `/api`
2. Copy the API key (use Reveal, then copy)
3. Call endpoints with `Authorization: Bearer <key>`

Example create order:

```bash
curl -X POST https://yoursite.com/api/external/orders \
  -H "Authorization: Bearer sk_xxx" \
  -H "Content-Type: application/json" \
  -d '{"external_order_id":"ORD-1","service":12,"link":"https://t.me/channel","quantity":500}'
```

## Files Changed

| File | Change |
|------|--------|
| `database/migrations/2026_03_20_000001_add_api_fields_to_clients_table.php` | New |
| `database/migrations/2026_03_20_000002_add_source_and_external_order_id_to_orders_table.php` | New |
| `app/Http/Middleware/AuthenticateApiClient.php` | New |
| `app/Models/Client.php` | API fields, casts |
| `app/Models/Order.php` | SOURCE_*, source, external_order_id |
| `app/Http/Controllers/External/ExternalOrderController.php` | Refactored |
| `app/Http/Controllers/External/ExternalServiceController.php` | New |
| `app/Http/Requests/External/ExternalOrderStoreRequest.php` | Updated (service) |
| `app/Services/OrderService.php` | createApiOrder, ApiOrderResult |
| `app/Services/Order/ApiOrderResult.php` | New |
| `app/Services/OrderServiceInterface.php` | createApiOrder |
| `app/Http/Controllers/Client/ApiController.php` | New |
| `app/Http/Controllers/Client/OrderController.php` | Source filter |
| `bootstrap/app.php` | auth.api_client alias |
| `routes/api.php` | auth.api_client, services, statuses |
| `routes/web.php` | client.api routes |
| `resources/views/client/api/index.blade.php` | New |
| `resources/views/client/orders/index.blade.php` | Source filter, badge |
| `resources/views/layouts/client-navigation.blade.php` | API nav link |
