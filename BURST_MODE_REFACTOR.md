# Burst Mode Refactor - Implementation Summary

## Overview
This refactor enables high-throughput execution without provider batch support while preserving all existing business logic, constraints, and safety mechanisms.

## Changes Made

### 1. Fixed TelegramStepCompletionService::handle() (CRITICAL FIX)
**File:** `app/Services/Telegram/TelegramStepCompletionService.php`

**Changes:**
- ✅ **Fixed counter updates**: `delivered` and `remains` now ONLY update when `$ok == true`
- ✅ **Respects `$perCall`**: Processed count is calculated as `max(1, $perCall)`
- ✅ **Prevents negative remains**: Uses `max(0, $currentRemains - $processed)` before updating
- ✅ **No updates on failure**: On failures, counters remain unchanged
- ✅ **Burst mode support**: Checks `execution_meta['mode']` and only dispatches next step in "serial" mode
- ✅ **Refresh order**: Refreshes order after updates to get latest `remains` value

**Safety:** All existing logic preserved, only correctness fixes applied.

### 2. Created DispatchTelegramOrderStepsJob (NEW)
**File:** `app/Jobs/DispatchTelegramOrderStepsJob.php`

**Purpose:** Dispatcher job for burst mode that manages step job dispatching without N^2 dispatch storms.

**Features:**
- Reads order state and `execution_meta` to determine burst configuration
- Checks in-flight counter per order (`tg:inflight:{orderId}`)
- Dispatches up to `(burst_window - inflight)` step jobs
- Caps dispatch per tick (default: 200) for fairness across orders
- Reschedules itself while order has remaining work and window has capacity
- Cleans up in-flight counter when order completes

**Redis Keys:**
- `tg:inflight:{orderId}` - In-flight counter with 300s TTL (refreshed on each change)

### 3. Created ExecuteTelegramSingleStepJob (NEW)
**File:** `app/Jobs/ExecuteTelegramSingleStepJob.php`

**Purpose:** Single step execution job extracted from `SendOrderToProvider::process()`.

**Features:**
- Contains core single-step logic: claim order, select account, execute provider step, handle completion
- Manages in-flight counter: increments on start, decrements in `finally` block (even on exceptions)
- Preserves provider throttling (reuses existing Redis throttle strategy)
- Supports burst mode optimizations in `selectAccount()`
- Handles pending/failed states same as original

**Safety:** All existing constraints preserved (cooldown, cap, dedupe, state gating, idempotency).

### 4. Updated SendOrderToProvider (MINIMAL CHANGES)
**File:** `app/Jobs/SendOrderToProvider.php`

**Changes:**
- Added mode check at start of `handle()` method
- For "burst" mode: triggers `DispatchTelegramOrderStepsJob` instead of processing directly
- For "serial" mode: continues with existing behavior (no changes)
- Added import for `DispatchTelegramOrderStepsJob`

**Safety:** Existing serial mode behavior completely unchanged.

### 5. Optimized selectAccount for Burst Mode (SAFE OPTIMIZATION)
**Location:** `ExecuteTelegramSingleStepJob::selectAccount()`

**Optimizations:**
- **Burst mode detection**: Checks `execution_meta['mode'] === 'burst'`
- **Reduced maxScanLimit**: 300 (burst) vs 2000 (serial) - makes failures fast
- **Increased batchSize**: 500 (burst) vs 200 (serial) - reduces total queries
- **Random cursor jump**: Adds `random_int(1, 5000)` to start cursor to reduce contention

**Safety:** Only applies optimizations when `mode === 'burst'`, serial mode unchanged.

### 6. Configuration Updates
**File:** `config/telegram.php`

**Added:**
```php
'account_selection' => [
    // ... existing config ...
    'burst_max_scan_limit' => 300,
    'burst_batch_size' => 500,
],

'burst' => [
    'max_dispatch_per_tick' => env('TELEGRAM_BURST_MAX_DISPATCH_PER_TICK', 200),
],
```

## Execution Modes

### Serial Mode (Default)
- **Mode:** `execution_meta['mode'] = 'serial'` (or missing)
- **Behavior:** One step at a time, dispatches next step from `TelegramStepCompletionService` after delay
- **Use case:** Existing behavior, unchanged

### Burst Mode
- **Mode:** `execution_meta['mode'] = 'burst'`
- **Configuration:**
  - `execution_meta['burst_window']` - Max in-flight step jobs per order (default: 10)
  - `execution_meta['queue']` - Queue name for step jobs
- **Behavior:**
  1. `SendOrderToProvider` triggers `DispatchTelegramOrderStepsJob`
  2. Dispatcher checks in-flight counter and dispatches up to `burst_window` step jobs
  3. Step jobs execute concurrently, managing in-flight counter
  4. `TelegramStepCompletionService` does NOT dispatch next step (dispatcher handles it)
  5. Dispatcher reschedules itself while order has work

## Redis Keys and TTLs

All Redis keys use TTL to prevent leaked state:

- `tg:inflight:{orderId}` - 300 seconds (refreshed on each increment/decrement)
- `tg:acct_cursor:global` - No TTL (intentional, for fair rotation)

## Testing Checklist

### Correctness Tests

1. **remains/delivered never move on failure**
   - Create order with `remains = 100`
   - Force provider to return `ok = false`
   - Verify: `delivered` unchanged, `remains` still 100

2. **remains never becomes negative**
   - Create order with `remains = 1`, `per_call = 5`
   - Execute successful step
   - Verify: `remains = 0` (not negative)

3. **perCall is respected**
   - Create order with `per_call = 10`
   - Execute successful step
   - Verify: `delivered` increases by 10, `remains` decreases by 10

4. **Burst mode keeps inflight <= burst_window**
   - Create order with `mode = 'burst'`, `burst_window = 5`
   - Trigger dispatcher
   - Verify: Redis `tg:inflight:{orderId}` <= 5

5. **Multiple orders progress concurrently**
   - Create 3 orders with `mode = 'burst'`
   - Verify: All orders make progress, no starvation

6. **Throttle logs show allow/block as expected**
   - Monitor logs for `PROVIDER_THROTTLE_ALLOW` and `PROVIDER_THROTTLE_BLOCK`
   - Verify: Throttling works correctly

### Integration Tests

7. **Serial mode unchanged**
   - Create order without `mode` field (defaults to serial)
   - Verify: Behavior identical to pre-refactor

8. **Burst mode dispatcher reschedules**
   - Create order with `mode = 'burst'`, `remains = 1000`
   - Verify: Dispatcher reschedules itself while order has work

9. **In-flight counter cleanup**
   - Complete order in burst mode
   - Verify: `tg:inflight:{orderId}` is deleted

10. **Provider pending tasks still work**
    - Force provider to return `state = 'pending'`
    - Verify: `PollProviderTaskStatus` is dispatched, order status updated

## Migration Notes

### No Database Changes Required
- All new fields are in `execution_meta` JSON (already exists)
- No migrations needed

### Backward Compatibility
- ✅ Serial mode (default) behavior unchanged
- ✅ Existing orders continue to work
- ✅ New burst mode is opt-in via `execution_meta['mode'] = 'burst'`

### Configuration
- New config values have safe defaults
- Can be overridden via environment variables if needed

## Safety Guarantees

✅ **Cooldown preserved**: Account cooldown checks unchanged
✅ **Daily cap preserved**: Cap service logic unchanged  
✅ **Dedupe preserved**: Deduplication logic unchanged
✅ **State gating preserved**: Subscribe/unsubscribe state checks unchanged
✅ **Idempotency preserved**: Order claiming and locking unchanged
✅ **Provider throttling preserved**: Redis throttle strategy unchanged
✅ **Retry/pending preserved**: `PollProviderTaskStatus` flow unchanged

## Performance Improvements

- **Burst mode**: Enables concurrent execution without provider batch support
- **Account selection**: Faster failures in burst mode (300 vs 2000 scan limit)
- **Reduced contention**: Random cursor jump reduces contention in burst mode
- **Fairness**: Max dispatch per tick ensures multiple orders don't starve each other

## Known Limitations

- Burst mode requires provider to support 1 request = 1 action (no batch support)
- In-flight counter uses Redis (single point of coordination)
- Dispatcher runs every 1-2 seconds (configurable via reschedule delay)

## Future Improvements (Out of Scope)

- Redis connection pooling (separate improvement)
- Dynamic burst_window adjustment based on provider capacity
- Per-order priority queuing

