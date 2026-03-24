# App Task Claim Logic

App performer claim flow for app download + review services (App Store / Google Play). Mirrors the YouTube claim pattern: one task per `account_identity`, with per-account `comment_text` and `star_rating` in the response and service description.

---

## 1. Overview

- **Entry point:** Provider calls the claim API with `account_identity` (e.g. phone or external account id).
- **Goal:** Return at most one task per request: one order’s link + action for that account to execute.
- **Uniqueness:** Same account + same target (target_hash) + same action → only once.

---

## 2. API Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /api/provider/app/orders-list` | List awaiting/in-progress App orders (optional) |
| `GET /api/provider/app/getOrder` | Claim one task (requires `account_identity`) |
| `GET /api/provider/app/check` | Report task success |
| `GET /api/provider/app/ignore` | Report task failure |

### Orders List

**Endpoint:** `GET /api/provider/app/orders-list`

Returns all awaiting App orders. Optional — for informational or pre-fetch use.

**Response (200):**
```json
{
  "ok": true,
  "count": 1,
  "orders": [
    {
      "id": 123,
      "link": "https://play.google.com/store/apps/details?id=...",
      "quantity": 100,
      "delivered": 0,
      "remains": 100,
      "status": "awaiting",
      "service_id": 5,
      "service_name": "App Download + Positive Review",
      "service_description": "...",
      "category_id": 2,
      "category_name": "App",
      "star_rating": 5,
      "comment_text": "Great app!",
      "created_at": "2026-03-18T12:00:00.000000Z"
    }
  ]
}
```

`star_rating` and `comment_text` are included when present.

### Claim (getOrder)

| Item | Details |
|------|---------|
| **Controller** | `App\Http\Controllers\Api\Provider\AppTaskClaimController` |
| **Input** | `account_identity` (required, string) — performer identifier |
| **Output** | JSON: `ok`, `count`, `task_id`, `link`, `link_hash`, `action`, `order`, `service`, `comment_text`, `star_rating` |

---

## 3. comment_text per account_identity (like YouTube)

When the service step is `custom_review` and `order.comment_text` exists (can be multi-line):

1. Split by newline: `comments = array_values(array_filter(array_map('trim', explode("\n", order.comment_text))))`
2. Index: `index = order.delivered + inFlight` (in-flight = count of LEASED tasks for this order)
3. Pick one: `commentTextForTask = comments[index % count(comments)]`
4. Put in `payload['comment_text']`, API response `comment_text`, and append to `service.description`

Each account gets a different comment (task 0 → comment 0, task 1 → comment 1, etc.).

---

## 4. star_rating in description

When `provider_payload.star_rating` exists (1–5) and the step is `custom_review` or `positive_review`:

1. Add `star_rating` to the task `payload` and API response
2. Append to `service.description`: `"Star rating: X/5"` so the performer sees the required star rating

---

## 5. Data Sources Summary

| Data | Source |
|------|--------|
| **comment_text** | `order.comment_text` — one line per task, picked by index (delivered + inFlight) |
| **star_rating** | `order.star_rating` (or `provider_payload.star_rating` fallback) |
| **Action / steps** | `provider_payload.execution_meta` (from inspection) |
| **Target hash** | `AppTargetNormalizer::targetHash($order)` |

---

## 6. Files Reference

| Component | File |
|-----------|------|
| Orders list API | `app/Http/Controllers/Api/Provider/AppAwaitingOrdersController.php` |
| Claim API | `app/Http/Controllers/Api/Provider/AppTaskClaimController.php` |
| Claim logic | `app/Services/App/AppTaskClaimService.php` |
| Report logic | `app/Services/App/AppTaskService.php` |
| Execution plan | `app/Services/App/AppExecutionPlanResolver.php` |
| Target hashing | `app/Support/App/AppTargetNormalizer.php` |
| Task model | `app/Models/AppTask.php` |
