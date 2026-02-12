# Auto-Unsubscribe Implementation

## Overview

Implemented automatic unsubscribe scheduling that uses the same accounts that subscribed, after a configurable number of days (from `Service.duration_days`).

## Business Rules

- When a **subscribe** task completes successfully (`state=done` and `ok=true`), an unsubscribe task is scheduled if `Service.duration_days > 0`.
- The unsubscribe task uses the **same account** that performed the subscribe.
- The `due_at` timestamp is **deterministic** (based on `TelegramTask.created_at + duration_days`) to ensure idempotency.
- Unsubscribe tasks are generated into `telegram_tasks` table when `due_at <= now()`.
- Unsubscribe tasks are finalized when the unsubscribe action completes successfully.

## Implementation Details

### 1. Model Updates

**`app/Models/TelegramUnsubscribeTask.php`**
- Added status constants: `STATUS_PENDING`, `STATUS_PROCESSING`, `STATUS_DONE`, `STATUS_FAILED`
- Added `telegram_task_id` to `$fillable`
- Added `subject()` morphTo relation for Order linkage
- Added `telegramTask()` belongsTo relation

### 2. Database Migration

**`database/migrations/2026_01_21_143440_add_telegram_task_id_to_telegram_unsubscribe_tasks_table.php`**
- Added `telegram_task_id` column (nullable, foreign key to `telegram_tasks.id`)
- Added index on `telegram_task_id`

### 3. Service Updates

**`app/Services/Telegram/TelegramTaskService.php`**

#### New Methods:

1. **`scheduleUnsubscribeTask()`** - Called when subscribe succeeds
   - Checks `Service.duration_days > 0`
   - Uses `updateOrCreate()` with unique constraint for idempotency
   - Sets `due_at = task.created_at + duration_days` (deterministic)

2. **`finalizeUnsubscribeTask()`** - Called when unsubscribe succeeds
   - Finds matching unsubscribe task by `account_id`, `link_hash`, `subject_id`
   - Marks as `STATUS_DONE`

3. **`generateUnsubscribeTasks()`** - Generates tasks from due unsubscribe tasks
   - Finds pending tasks where `due_at <= now()`
   - Reserves the same account using two-phase claim (RESERVE only)
   - Creates `TelegramTask` with `action='unsubscribe'`
   - Marks unsubscribe task as `STATUS_PROCESSING` and links to `telegram_task_id`

4. **`generateTaskFromUnsubscribeTask()`** - Helper to create TelegramTask from TelegramUnsubscribeTask
   - Reserves account with two-phase claim
   - Creates task with same account and link_hash
   - Links unsubscribe task to telegram task

5. **`handleUnsubscribeTaskFailure()`** - Handles unsubscribe task failures
   - Reverts unsubscribe task to `STATUS_PENDING`
   - Adds 1 hour backoff to `due_at`
   - Clears `telegram_task_id` link

#### Updated Methods:

1. **`generateTasks()`** - Now has two phases:
   - Phase 1: Generate tasks from eligible orders (existing logic)
   - Phase 2: Generate tasks from due unsubscribe tasks (new)

2. **`reportTaskResult()`** - Updated to:
   - Call `scheduleUnsubscribeTask()` when subscribe succeeds
   - Call `finalizeUnsubscribeTask()` when unsubscribe succeeds
   - Call `handleUnsubscribeTaskFailure()` when unsubscribe fails
   - Use `$task->action` instead of `execution_meta['action']` for reliability

## Two-Phase Claim Semantics

- **RESERVE** (when creating task): Sets short lock, checks state/dedupe, does NOT consume cap/cooldown
- **COMMIT** (on success report): Consumes cooldown, cap, sets state, releases lock
- **ROLLBACK** (on failure): Releases lock only, does NOT rollback cap/cooldown

## Idempotency

- **Subscribe reports**: `updateOrCreate()` with unique constraint `(account_id, link_hash, due_at)` ensures no duplicates
- **Unsubscribe reports**: Checks for existing unsubscribe task in `STATUS_PROCESSING` before finalizing
- **Deterministic timing**: `due_at = task.created_at + duration_days` ensures same timestamp for duplicate reports

## Task Flow

1. **Subscribe Task Completes** → `scheduleUnsubscribeTask()` creates `TelegramUnsubscribeTask` with `due_at = task.created_at + duration_days`
2. **Generator Runs** → `generateUnsubscribeTasks()` finds due tasks, reserves account, creates `TelegramTask` (action=unsubscribe)
3. **Provider Pulls** → Provider pulls `TelegramTask` via `/api/provider/telegram/tasks/pull`
4. **Provider Reports** → Provider reports result via `/api/provider/telegram/tasks/report`
5. **Unsubscribe Completes** → `finalizeUnsubscribeTask()` marks `TelegramUnsubscribeTask` as `STATUS_DONE`

## Failure Handling

- **Reserve fails** (cooldown/cap/locked/state): Unsubscribe task remains `STATUS_PENDING`, will retry on next generation cycle
- **Unsubscribe fails**: Unsubscribe task reverts to `STATUS_PENDING` with 1 hour backoff, `telegram_task_id` cleared

## Tests

**`tests/Feature/TelegramUnsubscribeTaskTest.php`**

1. ✅ `test_subscribe_success_schedules_unsubscribe_task()` - Verifies unsubscribe task is created with correct `due_at`
2. ✅ `test_duplicate_subscribe_report_does_not_create_duplicate_unsubscribe_task()` - Verifies idempotency
3. ✅ `test_generator_converts_due_unsubscribe_task_to_telegram_task()` - Verifies task generation from due unsubscribe tasks
4. ✅ `test_unsubscribe_success_finalizes_unsubscribe_task()` - Verifies unsubscribe task is finalized on success
5. ✅ `test_no_unsubscribe_task_when_duration_days_is_zero()` - Verifies no unsubscribe task when `duration_days = 0`

## Configuration

No new configuration needed. Uses existing:
- `Service.duration_days` - Number of days after subscribe to schedule unsubscribe
- `telegram.action_policies.unsubscribe.*` - Unsubscribe action policies

## Notes

- Unsubscribe tasks are **internal** - provider only pulls from `telegram_tasks` table
- Same account is used for subscribe and unsubscribe (enforced by `telegram_account_id` in unsubscribe task)
- Deterministic `due_at` ensures idempotency even with duplicate subscribe reports
- Two-phase claim ensures cap/cooldown are only consumed on successful unsubscribe completion

