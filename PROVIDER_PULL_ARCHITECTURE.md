# Provider Pull Architecture - Implementation Summary

## Overview

This refactor transforms the current "push" provider execution (SendOrderToProvider -> executeTelegramStep HTTP call) into a "provider pull" architecture where the provider pulls tasks and reports results.

## Architecture Changes

### Old Architecture (Push)
- `SendOrderToProvider` job executes steps
- Direct HTTP calls to provider
- Provider responds synchronously or via webhook

### New Architecture (Pull)
- Core app generates tasks and stores in `telegram_tasks` table
- Provider pulls tasks via API endpoint
- Provider reports results via API endpoint
- Tasks are leased with TTL to prevent double-processing

## API Endpoints

### 1. POST /api/provider/telegram/accounts/sync
**Purpose:** Provider syncs Telegram accounts to core app

**Request:**
```json
{
  "accounts": [
    {
      "provider_account_id": "ext_123",
      "phone": "+1234567890",
      "is_active": true,
      "meta": {}
    }
  ]
}
```

**Response:**
```json
{
  "ok": true,
  "synced": 1,
  "errors": []
}
```

### 2. POST /api/provider/telegram/tasks/pull
**Purpose:** Provider pulls tasks (assignments) in batches

**Request:**
```json
{
  "limit": 1000
}
```

**Response:**
```json
{
  "ok": true,
  "tasks": [
    {
      "task_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "order_id": 123,
      "action": "subscribe",
      "link": "https://t.me/channel",
      "account": {
        "provider_account_id": "ext_123",
        "phone": "+1234567890"
      },
      "per_call": 1,
      "post_id": null,
      "link_hash": "abc123...",
      "attempt": 0
    }
  ],
  "count": 1
}
```

### 3. POST /api/provider/telegram/tasks/report
**Purpose:** Provider reports task result

**Request:**
```json
{
  "task_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "state": "done",
  "ok": true,
  "error": null,
  "retry_after": null,
  "provider_task_id": "provider_task_123",
  "data": {}
}
```

**Response:**
```json
{
  "ok": true
}
```

## Two-Phase Redis Claim System

### RESERVE Phase (Task Generation)
- Sets short lock key: `tg:lock:{action}:{accountId}` (120s TTL)
- Checks state gating (subscribe/unsubscribe)
- Checks dedupe (for one-shot actions)
- **Does NOT consume cooldown or daily cap**

### COMMIT Phase (Success Report)
- Consumes cooldown: `tg:cooldown:{action}:{accountId}` (TTL from config)
- Consumes daily cap: `tg:cap:{action}:{accountId}:{date}` (TTL until midnight)
- Sets state: `tg:link_state:{linkHash}:{accountId}` (90 days TTL)
- Sets dedupe key (for one-shot actions)
- Releases lock key

### ROLLBACK Phase (Failure Report)
- Releases lock key only
- **Does NOT rollback cooldown/cap** (they weren't consumed yet)

## Database Schema

### telegram_tasks Table
```sql
CREATE TABLE telegram_tasks (
    id VARCHAR(26) PRIMARY KEY,  -- ULID
    order_id BIGINT UNSIGNED,
    action VARCHAR(50),
    link_hash VARCHAR(64),
    telegram_account_id BIGINT UNSIGNED NULLABLE,
    provider_account_id VARCHAR(255) NULLABLE,
    status ENUM('queued', 'leased', 'pending', 'done', 'failed'),
    leased_until TIMESTAMP NULLABLE,
    attempt INT UNSIGNED DEFAULT 0,
    payload JSON,
    result JSON NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (status, leased_until),
    INDEX (order_id, status),
    INDEX (telegram_account_id, status)
);
```

### telegram_accounts Table (New Fields)
- `provider_account_id` VARCHAR(255) UNIQUE NULLABLE
- `meta` JSON NULLABLE

## Task Generation Rules

Tasks are generated when:
1. `order.next_run_at <= now()` (from `execution_meta.next_run_at`)
2. `order.remains > 0`
3. `order.status` in (`AWAITING`, `IN_PROGRESS`, `PENDING`)

After generating a task:
- `order.next_run_at` is advanced by `interval_seconds`

## Leasing System

- When tasks are pulled, they are marked as `leased` with `leased_until = now() + 60s`
- Tasks with expired leases become eligible again
- Prevents double-processing if provider doesn't report within TTL

## Account Selection

Uses existing `selectAccount` logic with cursor scanning:
- Redis-based atomic checks for cooldown, cap, dedupe, state gating
- Uses **reserve claim** (two-phase: RESERVE only)
- Account is reserved when task is created
- Account is committed when provider reports success

## Idempotency

- Task reports are idempotent: if task is already finalized (`done` or `failed`), report returns `ok` without double-applying
- Prevents double consumption of daily cap/cooldown

## Files Created/Modified

### New Files
1. `database/migrations/2026_01_21_125415_create_telegram_tasks_table.php`
2. `database/migrations/2026_01_21_125435_add_provider_fields_to_telegram_accounts_table.php`
3. `app/Models/TelegramTask.php`
4. `app/Services/Telegram/TelegramTaskService.php`
5. `app/Http/Controllers/Api/Provider/TelegramAccountSyncController.php`
6. `app/Http/Controllers/Api/Provider/TelegramTaskPullController.php`
7. `app/Http/Controllers/Api/Provider/TelegramTaskReportController.php`
8. `app/Http/Middleware/AuthenticateProvider.php`
9. `app/Console/Commands/GenerateTelegramTasks.php`
10. `tests/Feature/TelegramTaskServiceTest.php`

### Modified Files
1. `app/Services/Telegram/RedisClaimScripts.php` - Added reserve and commit scripts
2. `app/Services/Telegram/TelegramAccountClaimService.php` - Added reserve/commit/rollback methods
3. `app/Services/Telegram/TelegramStepCompletionService.php` - Added pull_mode check to prevent dispatch
4. `app/Models/TelegramAccount.php` - Added provider_account_id and meta fields
5. `routes/api.php` - Added provider pull endpoints
6. `bootstrap/app.php` - Registered auth.provider middleware
7. `config/services.php` - Added provider.token config

## Configuration

Add to `.env`:
```
PROVIDER_TOKEN=your_shared_secret_here
```

## Authentication

Provider endpoints are authenticated via `X-Provider-Token` header:
- Middleware: `AuthenticateProvider`
- Config: `services.provider.token`
- Uses `hash_equals()` for timing-safe comparison

## Task Generation Command

Run periodically (e.g., every minute):
```bash
php artisan telegram:tasks:generate --limit=1000
```

Or add to scheduler in `bootstrap/app.php`:
```php
$schedule->command('telegram:tasks:generate')
    ->everyMinute()
    ->withoutOverlapping();
```

## Backward Compatibility

- Old `SendOrderToProvider` job remains but is disabled for main flow
- Code compiles and tests pass
- Existing push architecture can coexist with pull architecture
- `TelegramStepCompletionService` handles both modes

## Testing Checklist

✅ **Lease behavior**: Task reappears after `leased_until` expires
✅ **Idempotent report**: Double report does not double-consume cap
✅ **Cap enforcement**: Cap is enforced after success commit
✅ **Cooldown enforcement**: Cooldown is set after success commit
✅ **State gating**: Subscribe/unsubscribe state is checked during reserve
✅ **Dedupe**: One-shot actions are deduplicated during reserve

## Migration Steps

1. Run migrations:
   ```bash
   php artisan migrate
   ```

2. Set provider token in `.env`:
   ```
   PROVIDER_TOKEN=your_secret_here
   ```

3. Configure provider to sync accounts:
   - POST to `/api/provider/telegram/accounts/sync`

4. Start task generation (scheduler or manual):
   ```bash
   php artisan telegram:tasks:generate
   ```

5. Provider starts pulling tasks:
   - POST to `/api/provider/telegram/tasks/pull`

6. Provider reports results:
   - POST to `/api/provider/telegram/tasks/report`

## Notes

- Tasks use ULID for unique IDs (26 characters)
- Leasing TTL is 60 seconds (configurable)
- Reserve lock TTL is 120 seconds
- State TTL is 90 days
- Daily cap resets at midnight
- Cooldown TTL is per-action (from config)

