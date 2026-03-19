# Fast Order (Guest Checkout) Documentation

Backend-only flow for guest users to create an order without an account. The real order and user are created only after payment success.

---

## Table of Contents

1. [Overview](#overview)
2. [Flow](#flow)
3. [Database](#database)
4. [API Endpoints](#api-endpoints)
5. [Validation](#validation)
6. [Conversion (Payment Success)](#conversion-payment-success)
7. [Configuration](#configuration)

---

## Overview

- **Goal:** Guest selects category, service, targets/links, quantity (and optional dripfeed, speed tier, etc.), submits a **draft**; later selects payment method; after (simulated or real) payment success, the system creates a **user** and **real order** and returns credentials.
- **Rules:** Drafts live in `fast_orders` only. The `orders` table holds only real orders. User and order are created only after payment success. Plain password is never stored; only hashed in DB and returned once in the conversion response.

---

## Flow

1. **Create draft** — `POST /api/fast-orders` with same payload shape as normal order (category_id, service_id, targets or comments/link, etc.). Backend validates via `StoreFastOrderRequest`, computes total (guest price), saves row in `fast_orders` with status `draft`, returns `uuid`.
2. **Set payment method** — `POST /api/fast-orders/{uuid}/payment-method` with `payment_method`. Status can move to `pending_payment`.
3. **Payment success** — Real payment callback (or for tests: `POST /api/fast-orders/{uuid}/simulate-payment-success`). Backend:
   - Generates unique email (e.g. `fastuser_xxxx@domain`) and random password.
   - Creates **Client** with hashed password and balance = order total.
   - Calls **OrderService::create()** with the stored payload (same as normal order creation).
   - Updates fast order: `status = converted`, `payment_status = paid`, `client_id`, `order_id`, `generated_email`.
   - Returns client, order, and **credentials** (email + plain password) for auto-login or popup.
4. **After that** — The created order is a normal order; task execution and processing use the existing pipeline from the `orders` table only.

---

## Database

### Table: `fast_orders`

| Column             | Type         | Description |
|--------------------|--------------|-------------|
| id                 | bigint       | Primary key |
| uuid               | uuid         | Unique, public identifier |
| category_id        | FK categories| |
| service_id         | FK services  | |
| payload            | json         | Normalized order payload for OrderService |
| status             | string       | draft, pending_payment, paid, converted, failed, expired |
| payment_method     | string, null | |
| payment_status     | string, null | unpaid, paid, failed |
| payment_reference  | string, null | e.g. gateway transaction id |
| total_amount       | decimal      | Guest total |
| currency           | string       | e.g. USD |
| generated_email    | string, null | Set after conversion |
| client_id          | FK clients, null | Set after conversion |
| order_id           | FK orders, null  | First created order after conversion |
| expires_at         | timestamp, null | Optional TTL for draft |
| created_at, updated_at | timestamps | |

---

## API Endpoints

Base path: `/api` (no auth for these endpoints).

| Method | Path | Description |
|--------|------|-------------|
| POST   | `/fast-orders` | Create draft. Body: same as normal order (category_id, service_id, targets or comments/link, dripfeed, speed_tier, etc.) |
| GET    | `/fast-orders/{uuid}` | Get fast order details (debug). |
| POST   | `/fast-orders/{uuid}/payment-method` | Set payment method. Body: `{"payment_method": "card"}`. |
| POST   | `/fast-orders/{uuid}/simulate-payment-success` | Simulate payment; create user + order, return credentials. |

### Create draft — request body (example)

```json
{
  "category_id": 1,
  "service_id": 5,
  "targets": [
    { "link": "https://t.me/channel", "quantity": 1000 }
  ]
}
```

Optional: `dripfeed_enabled`, `dripfeed_quantity`, `dripfeed_interval`, `dripfeed_interval_unit`, `speed_tier` (if service supports).

### Create draft — response (201)

```json
{
  "message": "Fast order draft created.",
  "fast_order": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "draft",
    "payment_status": "unpaid",
    "total_amount": "10.50",
    "currency": "USD"
  }
}
```

### Simulate payment success — response (200)

```json
{
  "message": "Payment simulated; account and order created.",
  "fast_order": { "uuid": "...", "status": "converted", "order_id": 123, "client_id": 456 },
  "client": { "id": 456, "email": "fastuser_xxxx@domain", "name": "Fast Order User" },
  "order": { "id": 123, "batch_id": "...", "status": "validating", "quantity": 1000, "charge": "10.50" },
  "credentials": {
    "email": "fastuser_xxxx@domain",
    "password": "plain_password_only_in_this_response"
  }
}
```

---

## Validation

- **StoreFastOrderRequest** mirrors **StoreOrderRequest**: category_id, service_id, service exists and belongs to category and is active, targets/links by type (regular, custom_comments, invite_subscribers), link validation via category `link_driver`, dripfeed and speed_tier when applicable.
- The validated **payload** is the same structure passed to **OrderService::create()** so conversion does not duplicate business rules.

---

## Conversion (Payment Success)

- **FastOrderService::markAsPaidAndConvert(FastOrder)**:
  1. Validates fast order is in a convertible state (draft or pending_payment).
  2. Generates unique guest email (config: `config('fast_order.guest_email_domain')`).
  3. Generates random password (not stored in DB).
  4. Creates **Client** (name, email, password hashed, balance = total_amount).
  5. Calls **OrderService::create($client, $fastOrder->getOrderPayload(), null)**.
  6. Updates fast order (status, payment_status, generated_email, client_id, order_id).
  7. Returns structured response with client, order, credentials.

- **Future real payment:** The gateway callback should resolve the FastOrder (e.g. by `payment_reference` or uuid) and call the same `markAsPaidAndConvert()` so conversion logic stays in one place.

---

## Configuration

- **config/fast_order.php**
  - `guest_email_domain` — Domain for generated emails (env: `FAST_ORDER_GUEST_EMAIL_DOMAIN`, default `fastorder.local`).

- **Pricing**
  - **PricingService::priceForGuest(Service)** — Used for draft total (default rate, no client discount).

---

## Key Classes

| Class | Role |
|-------|------|
| **FastOrder** | Model; relations: category, service, client, order; `getOrderPayload()` |
| **FastOrderService** | createDraft(), setPaymentMethod(), markAsPaidAndConvert() |
| **StoreFastOrderRequest** | Validation + payload() for fast order create |
| **FastOrderController** | store, show, setPaymentMethod, simulatePaymentSuccess |
| **OrderService** | Reused for real order creation in conversion |
