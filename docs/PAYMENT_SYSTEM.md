# Payment System Documentation

This document describes how the payment system works in the application: architecture, flow, configuration, and extension points.

---

## 1. Overview

The payment system supports **balance top-up** via external payment gateways. It uses:

- **Strategy + Resolver (Factory)** pattern for multiple providers
- **State Machine** for strict payment status transitions
- **Idempotent webhooks** (event deduplication)
- **Balance credit only on webhook** — redirects never credit; balance is updated only when the provider sends a PAID webhook

### Supported Providers

| Provider | Description |
|----------|-------------|
| heleket | Cryptocurrency payment gateway (Heleket API) |

---

## 2. Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           USER FLOW                                      │
├─────────────────────────────────────────────────────────────────────────┤
│  1. Client visits /balance/add                                          │
│  2. Selects Heleket, enters amount, submits form                        │
│  3. App calls InitiatePaymentService → Heleket API                      │
│  4. User redirected to Heleket pay_url                                  │
│  5. User pays on Heleket                                                │
│  6. Heleket redirects to /balance/add?status=success&... (display only) │
│  7. Heleket sends webhook POST → /api/webhooks/payments/heleket         │
│  8. HandleWebhookService processes → credits balance (ONLY here)        │
└─────────────────────────────────────────────────────────────────────────┘
```

### Layer Structure

```
app/
├── Domain/Payments/           # Contracts, DTOs, enums (vendor-agnostic)
├── Application/Payments/      # Services, state machine, resolver
├── Infrastructure/Payments/   # Provider implementations (Heleket)
└── Http/Controllers/          # API + web controllers
```

---

## 3. Components

### 3.1 Domain Layer

**PaymentGatewayInterface** — contract for all gateways:
- `initiate(PaymentIntent $intent): PaymentInitiationResult`
- `parseWebhook(string $rawBody, array $headers, string $ip): GatewayWebhookEvent`

**PaymentStatus** enum: `NEW`, `PENDING`, `PAID`, `FAILED`, `EXPIRED`, `REFUNDED`

**DTOs:**
- `PaymentIntent` — order_id, amount, currency, provider, url_success, url_return, webhook_url
- `PaymentInitiationResult` — provider, providerRef, payUrl, raw
- `GatewayWebhookEvent` — orderId, providerRef, status, raw

### 3.2 Application Layer

**PaymentGatewayResolver** — resolves gateway by provider key:
```php
$gateway = $resolver->resolve('heleket');
```

**PaymentStateMachine** — allowed transitions:
```
new     → pending, failed, expired
pending → paid, failed, expired
paid    → refunded
failed  → (terminal)
expired → (terminal)
refunded→ (terminal)
```

**InitiatePaymentService** — starts a payment:
1. Builds `PaymentIntent` with URLs
2. Resolves gateway and calls `initiate()`
3. Creates `Payment` record with status PENDING
4. Returns `pay_url`, `provider_ref`, `status`

**HandleWebhookService** — handles provider webhooks:
1. Parses and validates webhook (IP + signature)
2. Finds Payment by order_id + provider
3. Skips if event_hash already processed (idempotency)
4. Applies state transition
5. On first PAID: creates `BalanceLedgerEntry` and updates `Client.balance`
6. Creates `PaymentEvent` for audit

### 3.3 Infrastructure Layer (Heleket)

**HeleketClient** — API client:
- POST JSON with auth headers: `merchant`, `sign = md5(base64_encode(json) . API_KEY)`

**HeleketGateway** — implements `PaymentGatewayInterface`:
- `initiate()`: POST /v1/payment, returns uuid + url
- `parseWebhook()`: validates IP allowlist (optional), signature, maps status

**HeleketTestWebhookClient** — sends test webhooks (POST /v1/test-webhook/payment)

---

## 4. Flow Details

### 4.1 Initiation (Balance Top-up)

1. **Web UI**: User submits form on `/balance/add` with amount and provider (e.g. heleket).
2. **BalanceController::store** calls `InitiatePaymentService::run()`.
3. **InitiatePaymentService**:
   - Builds URLs:
     - `url_success`: `{APP_URL}/balance/add?status=success&provider=heleket&order_id={order_id}`
     - `url_return`: `{APP_URL}/balance/add?status=return&provider=heleket&order_id={order_id}`
     - `webhook_url`: `{APP_URL}/api/webhooks/payments/heleket`
   - Calls gateway `initiate()`
   - Saves Payment (order_id, provider_ref, pay_url, status=PENDING, client_id)
   - Returns pay_url for redirect
4. User is redirected to Heleket pay_url.

### 4.2 Redirect (No Balance Credit)

When Heleket redirects back to `/balance/add?status=success&provider=heleket&order_id=...`:
- **BalanceController::create** loads Payment from DB
- Displays status message based on `payment.status` (e.g. "Payment received, balance will be updated shortly")
- **No balance change** happens here

### 4.3 Webhook (Balance Credit)

When Heleket sends `POST /api/webhooks/payments/heleket`:
1. **PaymentWebhookController** reads raw body, resolves provider.
2. **HandleWebhookService::handle()**:
   - `HeleketGateway::parseWebhook()` validates IP (if enforced) and signature
   - Maps provider status to `PaymentStatus` (e.g. paid_over → PAID)
   - Finds Payment by order_id + provider
   - Uses `event_hash = sha256(rawBody)` for idempotency
   - Applies `PaymentStateMachine::transition()`
   - If status becomes PAID for the first time:
     - Creates `BalanceLedgerEntry` (credit)
     - Updates `Client.balance`
   - Creates `PaymentEvent` for audit
3. Returns 200 OK (or 400 on invalid signature).

---

## 5. API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | /api/payment-methods | — | List enabled payment methods |
| POST | /api/clients/{client}/balance/topup | auth:client | Initiate balance top-up |
| POST | /api/payments/{provider}/initiate | — | Generic payment initiation |
| POST | /api/webhooks/payments/{provider} | — | Webhook (validated by signature) |

### Web Routes

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | /balance/add | auth:client | Add balance form + status display |
| POST | /balance | auth:client | Submit top-up (redirects to pay_url) |

---

## 6. Configuration

### config/payments.php

```php
'enabled_providers' => ['heleket'],
'methods' => [
    'heleket' => [
        'code' => 'heleket',
        'title' => 'Cryptocurrency (Heleket)',
        'notes' => 'Pay with crypto via Heleket gateway.',
    ],
],
```

### config/services.php (Heleket)

```php
'heleket' => [
    'base' => env('HELEKET_API_BASE', 'https://api.heleket.com'),
    'merchant' => env('HELEKET_MERCHANT'),
    'payment_key' => env('HELEKET_PAYMENT_KEY'),
    'webhook_ip' => env('HELEKET_WEBHOOK_IP', '31.133.220.8'),
    'enforce_webhook_ip' => env('HELEKET_ENFORCE_WEBHOOK_IP', true),
],
```

### .env

```
APP_URL=http://127.0.0.1:8000
HELEKET_MERCHANT=...
HELEKET_PAYMENT_KEY=...
HELEKET_WEBHOOK_IP=31.133.220.8
HELEKET_ENFORCE_WEBHOOK_IP=false   # Set false for local ngrok testing
```

---

## 7. Database Schema

### payments
- id, client_id, order_id (unique), provider, provider_ref, amount, currency, status, pay_url, meta, paid_at, timestamps

### payment_events
- id, payment_id, provider, provider_ref, event_hash (unique), status, payload, timestamps

### balance_ledger_entries
- id, client_id, payment_id, amount_decimal, currency, type (credit/debit), meta, timestamps
- Unique (payment_id, type) for idempotency on credits

---

## 8. Security

- **Webhook signature**: `md5(base64_encode(json_encode(body_without_sign)) . PAYMENT_KEY)` — verified with `hash_equals()`
- **IP allowlist**: optional via `HELEKET_ENFORCE_WEBHOOK_IP`
- **Idempotency**: event_hash deduplication; paid_at check prevents double credit

---

## 9. Testing

### Artisan Command

```bash
php artisan heleket:test-webhook-payment paid --order_id=balance_1_xxx
```

### Tests (tests/Feature/Payments/)

- `HeleketPaymentTest` — initiate, webhook idempotency, status mapping
- `BalanceTopupTest` — topup with provider, invalid provider, payment-methods
- `HeleketTestWebhookCommandTest` — command endpoint and signature

---

## 10. Adding a New Provider

1. Implement `PaymentGatewayInterface` in `app/Infrastructure/Payments/{Provider}/`
2. Register in `PaymentGatewayResolver` (or via config)
3. Add to `config/payments.php` `enabled_providers` and `methods`
4. Add config in `config/services.php` and `PaymentServiceProvider`
