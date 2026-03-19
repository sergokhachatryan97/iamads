# YouTube Task Claim Logic

This document describes how YouTube tasks are claimed by performers (accounts), how uniqueness is enforced per account + target + action, and how the flow interacts with orders, dripfeed, and reporting.

---

## 1. Overview

- **Entry point:** Provider (performer) calls the claim API with `account_identity` (e.g. phone or external account id).
- **Goal:** Return at most one task per request: one order’s link + action for that account to execute.
- **Uniqueness (YouTube only):** An account may perform a given **action** on a given **target** (link) only once. Repeat claims for the same (account, target, action) are blocked.

---

## 2. API Entry Point

| Item | Details |
|------|--------|
| **Controller** | `App\Http\Controllers\Api\Provider\YouTubeTaskClaimController` |
| **Method** | `claim(Request $request)` |
| **Input** | `account_identity` (required, string) — performer identifier |
| **Output** | JSON: `ok`, `count` (0 or 1), `task_id`, `link`, `link_hash`, `action`, `target`, `order`, `service` |

If no task can be claimed, the response is `ok: true`, `count: 0`, `tasks: []`, and null/empty task fields.

---

## 3. Claim Flow (YouTubeTaskClaimService)

### 3.1 High-level steps

1. Load eligible YouTube orders (awaiting/in progress/pending, `remains > 0`).
2. Filter by **due time** (dripfeed or `execution_meta.next_run_at`).
3. For each due order (by due time, then by order id), try to claim one task for the given `account_identity`.
4. Return the first successful claim payload, or `null` if none.

### 3.2 Order eligibility

- **Status:** `AWAITING`, `IN_PROGRESS`, or `PENDING`
- **Remains:** `> 0`
- **Category:** `link_driver = 'youtube'`
- **Due:**  
  - If dripfeed: `dripfeed_next_run_at <= now` and run quota not exhausted.  
  - If not dripfeed: `execution_meta.next_run_at <= now` (or no next_run_at).

### 3.3 Per-order claim attempt (`tryClaimForOrder`)

Done inside a **DB transaction** with row lock on the order.

1. **Reload order** under lock, ensure still eligible (`remains > 0`, YouTube category).

2. **Resolve action**  
   From `provider_payload.execution_meta.action` (default `'view'`).

3. **Link and hashes**  
   - `link` = `order.link`  
   - `linkHash` = `YouTubeTargetNormalizer::linkHash($link)` (SHA256 of normalized URL)  
   - `targetHashForLog` = `provider_payload.youtube.parsed.target_hash ?? linkHash`  
   Used for global “already performed” check and for task identity.

4. **Dripfeed gating**  
   `OrderDripfeedClaimHelper::canClaimTaskNow($order)`. If dripfeed blocks (e.g. run quota filled, or next run in future), skip this order.

5. **In-flight cap**  
   Count existing tasks for this order in `LEASED` or `PENDING` with valid `leased_until`.  
   If `delivered + inFlight >= target_quantity`, skip (order already “full” for this round).

6. **Global “already performed” check (uniqueness)**  
   `ProviderActionLogService::hasPerformed('youtube', account_identity, targetHashForLog, action)`.  
   If **true**, this account has already performed this action on this target → **do not create a task** (return null, try next order).  
   This enforces: **same account + same target + same action = only once**.

7. **Subscribe (stateful) handling**  
   If action is `subscribe`:
   - Resolve target from `YouTubeTargetNormalizer::forSubscribeTarget($order)` → `target_type`, `normalized_target`, `target_hash` (channel/video/handle etc.).
   - Ensure global state row in `youtube_account_target_states`: create or update to `STATE_IN_PROGRESS` for this (account_identity, action, target_hash). If already `IN_PROGRESS` or `SUBSCRIBED`, skip this order.
   - On any later failure/duplicate, revert or delete that state.

8. **Per-order duplicate task**  
   If a task already exists for this (account_identity, order_id, link_hash, action), skip (and revert global state if subscribe was opened).

9. **Create YouTube task**  
   - `order_id`, `account_identity`, `action`, `link`, `link_hash`  
   - `target_hash` = subscribe target hash or `targetHashForLog` (so report can use same identity)  
   - `target_type`, `normalized_target` for subscribe  
   - `status` = `LEASED`, `leased_until` = now + 90s  
   - `payload`: `order_id`, `per_call`, `action`, and optionally `target_hash` / `normalized_target`  
   Unique constraint on the table prevents duplicate (account_identity, order_id, link_hash, action) at insert.

10. **Post-claim**  
    - Update global state’s `last_task_id` if subscribe.  
    - `OrderDripfeedClaimHelper::afterTaskClaimed($order)` (increment dripfeed run counter, maybe advance next run).  
    - Set order `status` = `IN_PROGRESS`.  
    - Return payload for API (task_id, link, action, order, service, etc.).

---

## 4. Uniqueness: provider_action_logs

- **Table:** `provider_action_logs`  
  Columns: `provider`, `account_identifier`, `target_hash`, `action`, `created_at`.  
  Unique index: `(provider, account_identifier, target_hash, action)`.

- **Used only for YouTube:**  
  - **At claim:** `hasPerformed('youtube', account_identity, targetHashForLog, action)`. If true → no task.  
  - **At report success:** `recordPerformed('youtube', account_identity, target_hash, action)` (see below).  
  So: same account + same target (hash) + same action → only one execution ever.

- **Target hash source:**  
  Prefer `provider_payload.youtube.parsed.target_hash` (from inspection); fallback `YouTubeTargetNormalizer::linkHash($link)`.

---

## 5. Report Flow (YouTubeTaskService)

When the performer reports task result (done/failed):

1. Load task; if already finalized (`DONE`/`FAILED`), return ok (idempotent).
2. Update task `result` and `status` from report (`PENDING` / `DONE` / `FAILED`).
3. If state is `pending`, stop (task still in progress).
4. If **ok and state = done**:
   - **Record in action log:**  
     `ProviderActionLogService::recordPerformed('youtube', task.account_identity, task.target_hash ?? task.link_hash, task.action)`.  
     This prevents the same account from being given the same (target, action) again.
   - Decrement order `remains`, increment `delivered`; if `delivered >= target_quantity`, mark order completed.
   - Apply dripfeed completion (e.g. advance run, next_run_at).
   - Set task status to `DONE` and update global state for subscribe if applicable.
5. If **not ok or failed**:
   - Rollback dripfeed claimed unit, set order `provider_last_error`, task `FAILED`, and update global state for subscribe.

---

## 6. Data Sources Summary

| Data | Source |
|------|--------|
| **Action** | `provider_payload.execution_meta.action` (set at inspection; e.g. view, react, comment, subscribe, watch) |
| **Target hash (for uniqueness)** | `provider_payload.youtube.parsed.target_hash` or `YouTubeTargetNormalizer::linkHash(link)` |
| **Link / link_hash** | `order.link`, `YouTubeTargetNormalizer::linkHash(link)` |
| **Subscribe target** | `YouTubeTargetNormalizer::forSubscribeTarget($order)` → channel_id / video / handle etc. and its hash |
| **Account identifier** | Request `account_identity` (performer id) |

---

## 7. Dripfeed

- **When order has dripfeed:**  
  Claim only if `dripfeed_next_run_at <= now` and current run’s delivered count is below run quota.  
  After a task is claimed, `OrderDripfeedClaimHelper::afterTaskClaimed` increments `dripfeed_delivered_in_run`; when run quota is reached, next run is scheduled (`dripfeed_run_index`, `dripfeed_next_run_at`).

- **On report success:**  
  `YouTubeTaskService` calls `applyDripfeedCompletionOnReport` so order and dripfeed counters stay in sync.

---

## 8. Stateful action (Subscribe)

- **Stateful action:** `subscribe` (constant `STATEFUL_ACTIONS`).
- **Table:** `youtube_account_target_states` — one row per (account_identity, action, target_hash).  
  States: e.g. `in_progress`, `subscribed`.
- **At claim:** Create or lock row for this (account_identity, subscribe, target_hash); if already in progress or subscribed, do not create a task for that order.
- **At report:** Update global state (e.g. subscribed) and link `last_task_id` to the task.

---

## 9. Task lifecycle

| Status | Meaning |
|--------|--------|
| `leased` | Task created and assigned; performer has until `leased_until` to execute and report |
| `pending` | Execution in progress (e.g. provider will report later) |
| `done` | Successfully executed and reported |
| `failed` | Reported as failed or error |

---

## 10. Files Reference

| Component | File |
|-----------|------|
| Claim API | `app/Http/Controllers/Api/Provider/YouTubeTaskClaimController.php` |
| Claim logic | `app/Services/YouTube/YouTubeTaskClaimService.php` |
| Report logic | `app/Services/YouTube/YouTubeTaskService.php` |
| Uniqueness (check + record) | `app/Services/ProviderActionLogService.php` |
| Dripfeed helpers | `app/Support/Performer/OrderDripfeedClaimHelper.php` |
| Target hashing / subscribe target | `app/Support/YouTube/YouTubeTargetNormalizer.php` |
| Task model | `app/Models/YouTubeTask.php` |
| Global state (subscribe) | `app/Models/YouTubeAccountTargetState.php` |
| Uniqueness table | `provider_action_logs` (migration: `create_provider_action_logs_table`) |

---

## 11. Behaviour Summary

- **One task per claim request:** At most one task per API call; first claimable order wins.
- **One execution per (account, target, action):** Enforced by `provider_action_logs` at claim (skip) and at report (insert). Same account can do different actions on the same target, or the same action on different targets.
- **Order/dripfeed:** Orders are chosen by due time and order id; dripfeed and in-flight counts prevent over-claiming.
- **Subscribe:** Global state and unique (account, action, target_hash) ensure subscribe is tracked and not duplicated across orders for the same channel.
