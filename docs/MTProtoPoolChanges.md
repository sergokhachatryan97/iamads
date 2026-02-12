# MTProto Pool Changes: Per-Proxy Throttle, Error Handling, Job Alignment

## Summary

Changes reduce MTProto burst when multiple queue jobs share the same HTTP proxy: per-proxy rate limiting, per-account lock retained, improved error classification and cooldown, Revolt error handler to avoid worker crash, and aligned job timeouts and dispatch spacing.

---

## 0. Latest refactor (pool + IPC / UnhandledFutureError fixes)

### TelegramMtprotoPoolService.php

- **Added `waitForProxyThrottleByMode($account, $mode)`** — The pool was calling this method but only `waitForProxyThrottle($account)` existed; the new method delegates to the throttle (mode can later drive different throttle durations).
- **IPC / “Could not connect to MadelineProto”** — When the exception message contains “Could not connect to MadelineProto” or “IPC”, the pool now:
  - Records failure as `IPC_UNAVAILABLE` (instead of generic STREAMISH).
  - Logs a warning with a **hint**: “Enable proc_open, remove open_basedir for workers, or use same PHP CLI as web.”
  - Applies proxy + account cooldown and retries (no immediate disable).
- **Revolt/EventLoop UnhandledFutureError** — The Revolt error handler now treats **UnhandledFutureError** that wrap `ClosedException` / “stream is not writable” as a **warning** with the note: “often after MTProto disconnect”. Other Revolt errors remain logged as error. This reduces log noise and clarifies that the stream error usually follows an MTProto disconnect.
- **`classifyGenericError()`** — Added classification for **IPC_UNAVAILABLE** (message contains “COULD NOT CONNECT TO MADELINEPROTO” or “IPC”).
- **Removed dead code** — Dropped the large commented-out block at the end of the file (old `executeWithPool` / `selectAvailableAccount` variants) and the unused `proxyLockKey()` method.

### MtprotoClientFactory.php

- **proc_open check** — When creating a runtime API (`requireSessionFile === true`), the factory checks if `proc_open` is disabled (via `disable_functions`). If so, it logs a **one-time warning**: “MTProto: proc_open is disabled; MadelineProto IPC may fail. Enable proc_open and relax open_basedir for queue workers.”
- **`isProcOpenDisabled()`** — New private helper: returns true if `proc_open` is not available or listed in `disable_functions`.

### Fixing the two errors you saw

1. **“Could not connect to MadelineProto, please enable proc_open and remove open_basedir…”**  
   MadelineProto starts an IPC helper process via `proc_open`. In queue workers (e.g. Horizon) you must:
   - **Enable `proc_open`** in PHP for the worker process (remove it from `disable_functions` in `php.ini` or the pool/FPM config used by workers).
   - **Relax or remove `open_basedir`** so the IPC process can run (or ensure the session and MadelineProto paths are inside `open_basedir`).
   - Use the **same PHP binary and extensions** for CLI and web if you run workers via `php artisan queue:work`; otherwise ensure the worker’s PHP has `proc_open` and a compatible env.

2. **“Unhandled future: Amp\ByteStream\ClosedException: The stream is not writable”**  
   This occurs when a stream is closed (e.g. after `forgetRuntimeInstance()` / `stop()`) while Amp still has a pending Future. The Revolt handler now logs it as a **warning** and does not crash the worker. To reduce how often it happens, the pool already calls `forgetRuntimeInstance()` on errors and applies cooldown; the warning is expected occasionally after an MTProto/connection failure.

---

## 1. Changelog / What Changed

### A) `app/Services/Telegram/MtprotoClientFactory.php`

- **Removed `$api->start()` from `createApi()`.**  
  The factory no longer starts the API instance. The pool (or any caller) must call `$madeline->start()` after obtaining the instance. This allows the pool to enforce per-proxy throttle and jitter **before** starting the connection.
- **Proxy configuration:** Unchanged. Proxy is still applied in `applyProxy()` for every account; session path and cache key include proxy so instances are not shared incorrectly.

### B) `app/Services/Telegram/TelegramMtprotoPoolService.php`

- **Per-proxy throttle (replaces proxy lock):**
  - After acquiring the per-account lock and before creating/using the API, the pool enforces a **per-proxy rate limit**.
  - Proxy key: `proxyThrottleKey($account)` — if account has proxy (proxy_type + proxy_host), key is `sha1(json_encode(proxy_fields))` (first 16 chars); else `'no_proxy'`.
  - Throttle: `Cache::add("tg:proxy:throttle:{proxyKey}", 1, $seconds)`. If `add()` fails (key exists), sleep 150–350 ms (jitter) and retry until allowed, up to 60 seconds.
  - Config: `config('telegram_mtproto.proxy_throttle_sec', 2)` — default **2 seconds** between calls per proxy. No new config file was added; if the key is missing, 2 is used.
- **Jitter before MTProto call:** 50–150 ms random `usleep` before the callback to avoid mechanical timing.
- **Pool calls `$madeline->start()`** after `makeForRuntime()` and after throttle/jitter, so connection starts only when the proxy slot is allowed.
- **Revolt/EventLoop error handler:** `ensureRevoltErrorHandler()` is called at the start of `executeWithPool()`. Uses a static flag so it is set only once per process. If `Revolt\EventLoop` exists, `setErrorHandler()` is set to log the exception and avoid worker crash. Handler does not rethrow.
- **Logging:** After each successful callback, log `account_id`, `proxy_key`, `elapsed_ms`, and `action` (e.g. `mtproto_call`).
- **Error classification:** New `classifyGenericError()`: FLOOD_WAIT, STREAM_CLOSED (including "stream is closed", `Amp\ByteStream\ClosedException`), SESSION_REVOKED, PEER_NOT_IN_DB, GENERIC.
- **Stream/closed cooldown:** In `handleGenericError()`, if the error is classified as STREAM_CLOSED, the account gets `setCooldown(60)` and `recordFailure('STREAM_CLOSED')` so burst retries don’t hammer the same proxy.
- **Removed proxy lock.** The previous per-proxy `Cache::lock()` and `proxyLock->block(2)` were removed; the throttle gate replaces them.

### C) `app/Jobs/SocpanelValidateOrderJob.php`

- **Timeout:** `$timeout` increased from 90 to **180** seconds so the job is not killed while waiting for throttle or MTProto responses.
- **Tries/backoff:** Unchanged (5 tries, backoff `[10, 30, 60, 120, 300]`).

### D) `app/Jobs/SocpanelPollOrdersJob.php`

- **Dispatch delay:** When dispatching `SocpanelValidateOrderJob`, a **random delay of 0–10 seconds** is applied via `->delay(now()->addSeconds(random_int(0, 10)))` so validations do not all start at the same time and reduce burst on the same proxy.

### E) `app/Console/Commands/TelegramAuthorizeAccounts.php`

- **Revolt error handler:** Before any long-running work, `ensureRevoltErrorHandler()` is called (static once per process). Same pattern as the pool: if `Revolt\EventLoop` exists, set an error handler that logs and does not rethrow, to avoid CLI crash on unhandled Revolt/Amp errors.

---

## 2. Config Keys (optional)

If you add them to `config/telegram_mtproto.php`:

| Key | Default | Description |
|-----|---------|-------------|
| `proxy_throttle_sec` | 2 | Seconds between MTProto calls per proxy (throttle TTL). |
| `call_deadline_ms` | 30000 | Pool deadline per attempt (existing). |
| `job_timeout_seconds` | 60 | Used for lock TTL (existing). |

If `proxy_throttle_sec` is not set, the code uses **2** seconds.

---

## 3. Runtime Behavior

1. **Per-account lock:** Still in place. `Cache::lock("tg:mtproto:lock:{account_id}", $lockTtl)` ensures only one consumer uses that account’s session at a time.
2. **Per-proxy throttle:** Before creating/using the API, the pool waits until `Cache::add("tg:proxy:throttle:{proxyKey}", 1, $seconds)` succeeds, with 150–350 ms jitter between retries. So all MTProto traffic through the same proxy is limited to about 1 call per `proxy_throttle_sec` seconds.
3. **Jitter:** 50–150 ms sleep before the actual MTProto call.
4. **Retry/cooldown:** FLOOD_WAIT and stream/closed errors still drive cooldown and failure recording; STREAM_CLOSED explicitly gets a 60 s cooldown. Existing RPC handling (FLOOD_WAIT, PEER_FLOOD, permanent disable) is unchanged.

---

## 4. IPC / proc_open / open_basedir (required for queue workers)

MadelineProto uses an **IPC subprocess** (started with `proc_open`) when running without an event handler in the same process. Queue workers (Horizon, `queue:work`) must allow this:

- **`proc_open`** must not be in PHP’s `disable_functions`.
- **`open_basedir`** (if set) must allow the session directory and MadelineProto to run the helper process; otherwise remove or relax it for the worker’s PHP.
- Prefer the **same PHP binary** (and extensions) for CLI and web so the IPC process matches the main process.

If these are not met, you will see: *“Could not connect to MadelineProto, please enable proc_open and remove open_basedir restrictions…”*. The pool now treats this as `IPC_UNAVAILABLE`, applies cooldown, and logs a hint. Fix the server/PHP config for the worker so `proc_open` is allowed and `open_basedir` does not block the IPC process.

---

## 5. Horizon / Worker Concurrency (operational guidance)

- **If all MTProto accounts use a single proxy:** Run only **1–2 worker processes** for the queue that runs MTProto-heavy jobs (e.g. `tg-inspect` or whatever runs validation). More processes will queue behind the same throttle and increase latency without increasing throughput; they can also increase timeouts and stream-closed errors.
- **Higher throughput:** Use **more proxies** (different proxy per account or per group of accounts), not more workers on the same proxy.
- **Where to set processes:** In Laravel Horizon, configure `config/horizon.php` (or your Horizon config) and set `maxProcesses` (or equivalent) for the relevant queue. This project may use `config/horizon.php`; if so, reduce `maxProcesses` for the queue that runs SocpanelValidateOrderJob / MTProto pool to 1–2 when all accounts share one proxy.

---

## 6. Revert Guide

### Option A: Git revert

If this was a single commit:

```bash
git revert <commit-hash> --no-edit
git push
```

If multiple commits:

```bash
git revert --no-commit <oldest-commit>^..<newest-commit>
git commit -m "Revert MTProto pool throttle and error handling"
git push
```

Then restart queue workers (and Horizon if used).

### Option B: Manual revert (per file)

1. **MtprotoClientFactory.php**  
   In `createApi()`, after `$api = new API($sessionPath, $settings);`, restore:
   ```php
   $api->start();
   ```
   Remove the comment that says "Do NOT call start() here; pool...".

2. **TelegramMtprotoPoolService.php**
   - Remove `ensureRevoltErrorHandler()` and the call to it at the start of `executeWithPool()`.
   - Remove `waitForProxyThrottle($account)` and the 50–150 ms jitter before the callback.
   - Remove the `$madeline->start();` line (so factory again owns start).
   - Restore the **proxy lock** block: after account lock, if `!empty($account->proxy_type)`, get `Cache::lock($proxyLockKey, 30)`, `block(2)`, and in `finally` call `optional($proxyLock)->release()`.
   - Remove `proxyThrottleKey()`, `waitForProxyThrottle()`, and the logging block that logs `account_id`, `proxy_key`, `elapsed_ms`, `action`.
   - Revert `handleGenericError()` to the previous version (no `classifyGenericError()`, no STREAM_CLOSED cooldown).
   - Remove `classifyGenericError()`.

3. **SocpanelValidateOrderJob.php**  
   Set `public $timeout = 90;` again.

4. **SocpanelPollOrdersJob.php**  
   Remove `->delay(now()->addSeconds($delaySeconds))` and the `$delaySeconds = random_int(0, 10);` line when dispatching `SocpanelValidateOrderJob`.

5. **TelegramAuthorizeAccounts.php**  
   Remove `ensureRevoltErrorHandler()`, the static `$revoltErrorHandlerSet`, and the `use Illuminate\Support\Facades\Log;` if unused.

### Config

- If you added `proxy_throttle_sec` to `config/telegram_mtproto.php`, remove it. The code uses a default of 2 when the key is absent, so no config change is required for revert.

After reverting, restart queue workers (and Horizon if used).
