# Telegram Inspection Pipeline Audit

## Section A: Flow explanation

### 1) Call chain (step-by-step)

- **Dispatch of `SocpanelValidateOrderJob`**
  - **`app/Jobs/SocpanelPollOrdersJob.php`**  
    - `handle()` runs poll under lock `socpanel:poll:{status}` (line 36).  
    - For each provider service, `pollServiceOrders()` (line 88) fetches orders from API, then for each item calls `processOrderItem()` (line 119+).  
    - `processOrderItem()` (lines 165–225): normalizes link via `normalizeTelegramLink($rawLink)` (line 169), creates/updates `ProviderOrder`, then calls **`dispatchValidateIfNeeded($order, $wasCreated)`** (line 225).  
  - **`dispatchValidateIfNeeded()`** (lines 257–291): checks `remains > 0`, status `VALIDATING`, `provider_sending_at` empty, and (if not `$wasCreated`) skips if `provider_last_error_at` within last 30s. Uses **Cache::lock(`socpanel:validate-group-dispatch:{sha1(serviceId|link)}`, 45 or 90)** then:
    - **`SocpanelValidateOrderJob::dispatch($serviceId, $link)->onQueue('tg-inspect')->delay(now()->addSeconds(random_int(0, 50)))->afterCommit()`** (lines 281–284).  
  - **No dedupe by (serviceId + normalizedLink)**: the lock is per `(serviceId|link)` but only prevents two dispatches for the *same* group from overlapping for 45–90s. Multiple poll runs can enqueue the same (serviceId, link) again after TTL, and different links with same serviceId each get a job. So **same (serviceId, normalizedLink) can accumulate many jobs** across runs.

- **`SocpanelValidateOrderJob`**
  - **`app/Jobs/SocpanelValidateOrderJob.php`**: `$timeout = 180`, `$tries = 5`, `$backoff = [10, 30, 60, 120, 300]`. No `$queue` property — queue is set at dispatch time: **`tg-inspect`**.  
  - **`handle(TelegramInspector $inspector)`** (line 187):  
    1. **Group lock**: `Cache::lock("socpanel:validate-group:{$groupKey}", 90)` where `$groupKey = sha1(serviceId|normalizedLink)` (lines 192–196). If lock not acquired, returns without doing work.  
    2. **Claim orders**: updates up to N orders with same (serviceId, link), status VALIDATING, remains>0, with `provider_sending_at = now()` (lines 199–227).  
    3. **Inspection cache**: `$cacheKey = 'tg:inspection:' . sha1(serviceId|normalizedLink)`; if cache has result, use it; else **`$inspectionResult = $inspector->inspect($this->normalizedLink)`** (line 236).  
    4. Applies validation rules and updates orders (depends_ok / depends_failed / validating for retry).

- **TelegramInspector → MTProto**
  - **`app/Services/Telegram/TelegramInspector.php`**  
    - **`inspect($link)`** (line 17): parses link; branches on `parsed['kind']`.  
    - **Bot links**: may call **`$this->mtprotoPool->resolveIsBotByUsername($username)`** (line 65).  
    - **Public username/post**: tries Bot API then **`$this->mtprotoPool->getInfoByUsername($username)`** (line 162). For paid check may call **`$this->mtprotoPool->getInfoByUsername($username)`** again (line 383).  
    - **Invite**: **`$this->mtprotoPool->checkInvite($hash)`** (line 250).  

- **TelegramMtprotoPoolService**
  - **`app/Services/Telegram/TelegramMtprotoPoolService.php`**  
    - **`getInfoByUsername($username)`** (line 50): **`executeWithPool(callback, MODE_INSPECT)`**; callback calls **`resolveUsernameWithApi($madeline, $username)`** which uses **`$madeline->getInfo($username)`** (line 694+).  
    - **`resolveIsBotByUsername($username)`** (line 76): same, **`executeWithPool(..., MODE_INSPECT)`** with **getInfo** in callback.  
    - **`checkInvite($hash)`** (line 107): **`executeWithPool(callback, MODE_INSPECT)`**; callback calls **`$madeline->messages->checkChatInvite(['hash' => $hash])`** (line 115).  

- **executeWithPool** (line 465)  
  - Uses **`config('telegram_mtproto.call_deadline_ms', 30000)`** and **`config('telegram_mtproto.max_accounts_to_try_per_call', 4)`** (lines 472–473).  
  - Loop: **`selectAvailableAccount($excludeIds, $mode, $excludeProxyKeys)`** (line 488) → if null, returns NO_AVAILABLE_ACCOUNTS with failWithMeta.  
  - Acquires **Cache::lock("tg:mtproto:lock:{account->id}", lockTtl)** (lines 510–512).  
  - Skips if **proxyHealth->isOnCooldown(proxyKey)** (lines 526–534).  
  - **Throttle**: **`waitForProxyThrottleByMode($account, $mode)`** (line 536) → **`waitForProxyThrottle()`** (line 1103): **blocks up to 60 seconds** in a loop doing `Cache::add('tg:proxy:throttle:' . throttleKey, 1, $seconds)` and **jitterSleepMs(150, 350)** until slot acquired or 60s elapsed. **No return value** — if 60s elapses without slot, execution continues anyway (then recordProxyCallWindow, jitterSleepMs(50,150), makeForRuntime, start, callback).  
  - **Peer-db handling** (lines 547–569): callback in try/catch; if message matches "PEER IS NOT PRESENT IN THE INTERNAL PEER DATABASE", forgets runtime, creates new Madeline, **retries callback once**. If retry throws, exception propagates to outer catch (RPCErrorException or Throwable).  
  - **RPCErrorException**: **handleRpcError()** (line 811); **PEER_NOT_IN_DB** is not a dedicated branch — falls through to default: **recordFailure, setCooldown(60), retry => true** (continue to next account).  
  - **Result**: success → return; non-retryable fail → return; retryable → continue. After loop, returns NO_AVAILABLE_ACCOUNTS (all attempts exhausted).

- **Proxy key (rotating vs static)**  
  - **`proxyThrottleKey($account)`** (line 1076): no proxy → `'no_proxy:' . $account->id`; with proxy → `$base = substr(sha1(json_encode(normalized)), 0, 16)`; **`config('telegram_mtproto.proxy_mode', 'rotating')`** → if **rotating**: `$base . ':acct:' . $account->id`; else **static**: `$base` only.  
  - So with **rotating** (your setup), each account has a **per-account** throttle/cooldown key even when proxy settings are identical (monoculture). Cooldown does not share across accounts.  
  - **proxy_mode** default in code is `'rotating'` at line 1083; config file does not define `proxy_mode` — so env or default `rotating` applies. **Confirm** `config('telegram_mtproto.proxy_mode')` in production.

---

### 2) Queue and concurrency

- **Queue name**: Job is pushed to **`tg-inspect`** via `->onQueue('tg-inspect')` at dispatch (SocpanelPollOrdersJob lines 282–283; OrderService uses `tg-inspect` for InspectTelegramLinkJob).  
- **Horizon** (`config/horizon.php`):  
  - **defaults['tg-inspect']** (lines 169–181): `queue => ['tg-inspect']`, **maxProcesses => 6**, **timeout => 210**, **tries => 1**, balance simple.  
  - **production** override (lines 246–250): **maxProcesses => 3** for tg-inspect.  
  - **local** (lines 268–270): **maxProcesses => 2**.  
- **Job duration / blocking**:  
  - **waitForProxyThrottle**: up to **60s** blocking per attempt (line 1109: `$maxWait = 60`).  
  - **jitterSleepMs**: 50–150ms after throttle; 8–30ms on lock miss; 15–60ms on proxy cooldown skip; 20–90ms or 25–120ms on retry.  
  - **call_deadline_ms**: 30000 (30s) — pool stops trying after 30s from start of executeWithPool.  
  - **Job timeout**: 180s (SocpanelValidateOrderJob line 22). So one job can block a worker for **up to ~60s (throttle) + 30s (deadline) + startup/Madeline** and still stay under 180s, but multiple attempts with throttle each can approach timeout.  
- **Retries**:  
  - **Laravel**: job has **tries = 5**, **backoff = [10, 30, 60, 120, 300]** — no explicit `retries` in handle, so failed jobs are retried by Horizon.  
  - **Internal pool**: executeWithPool tries up to **max_accounts_to_try_per_call** (default 8 in config, 4 in code default) accounts per *single* inspect call.  
  - **Temporary codes** in SocpanelValidateOrderJob (lines 255–261): MTPROTO_DEADLINE_EXCEEDED, NO_AVAILABLE_ACCOUNTS, etc. → order stays VALIDATING, **provider_sending_at** cleared → **poller can dispatch the same (serviceId, link) again** on next run. So: **Laravel retries the job** and/or **poller re-dispatches for same link** → potential **double retry / storm** for the same logical validation.

---

## Section B: Backlog causes (ranked by impact)

1. **Blocking throttle in worker (high)**  
   - **`waitForProxyThrottle()`** blocks up to **60s** with no way to fail fast or requeue. With 3–6 workers and shared throttle (or per-account in rotating), workers can sit waiting for the same slot → low throughput and jobs piling up.

2. **Dispatch rate > processing rate (high)**  
   - Poll runs (e.g. every minute) can push up to **MAX_VALIDATE_DISPATCH_PER_RUN = 250** (line 23) across services. Each job can take 30s–3min (throttle + deadline + retries). With 3 processes, throughput is ~3 jobs per 60–120s → backlog grows quickly.

3. **No dedupe of (serviceId + normalizedLink) (high)**  
   - **Not found**: no Cache::add key like `tg:inspect:dispatch:{sha1(serviceId|link)}` with TTL to prevent duplicate jobs for the same link. Same link can be enqueued many times across poll runs and retries → same inspection repeated, backlog inflated.

4. **Deadline and worker occupancy (medium)**  
   - **call_deadline_ms = 30000**: pool may try several accounts and wait for throttle each time; job holds worker for most of that. Combined with 60s throttle wait, one job can use 60s+ before failing with MTPROTO_DEADLINE_EXCEEDED. Lowering deadline for inspect reduces occupancy but may increase NO_AVAILABLE_ACCOUNTS.

5. **Locks that can stall (medium)**  
   - **socpanel:validate-group:{groupKey}** (90s): one worker per (serviceId, link) — good.  
   - **socpanel:validate-group-dispatch:{groupKey}** (45–90s): prevents overlapping dispatch for same group; after TTL, poll can dispatch again.  
   - **tg:mtproto:lock:{account->id}**: per-account; with many accounts less of a bottleneck; with monoculture, throttle is the main limiter.

6. **Peer-db and CANCELLED (medium)**  
   - Peer-db is retried once with fresh runtime; if it fails again, exception goes to handleRpcError (if RPC) or handleGenericError — both continue to next account. So "skip account" behavior exists. **Missing**: explicit log with **rid / account_id / proxy_key** and "peer_db_retry_failed" for observability.  
   - CANCELLED (e.g. Amp) marks proxy error and cooldown, then continue — no infinite loop, but adds to load and can contribute to cooldowns.

7. **Rotating proxy key strategy (already correct for monoculture)**  
   - **proxy_mode = 'rotating'** (default in code) → **per-account** proxyThrottleKey. So cooldown/throttle do not share across accounts; monoculture is not starved by a single shared key. **Recommendation**: ensure **telegram_mtproto.proxy_mode** is set to **rotating** in config/env for your rotating proxy.

8. **Potential “infinite” re-dispatch (low–medium)**  
   - If validation always returns temporary (e.g. MTPROTO_DEADLINE_EXCEEDED) and order stays VALIDATING with provider_sending_at cleared, every poll can dispatch again. Dedupe (fix 3) plus bounded throttle (fix 2) and lower deadline (fix 4) reduce how often the same link is worked on and how long each job holds the worker.

---

## Section C: Fixes (code diffs)

### C.1) Job dispatch dedupe (SocpanelPollOrdersJob)

**File:** `app/Jobs/SocpanelPollOrdersJob.php`

Add a short TTL dedupe so the same (serviceId, normalizedLink) does not get more than one job enqueued within the TTL.

```diff
     private function dispatchValidateIfNeeded(ProviderOrder $order, bool $wasCreated): bool
     {
         if ((int)$order->remains <= 0) return false;
         if ($order->status !== Order::STATUS_VALIDATING) return false;
         if (!empty($order->provider_sending_at)) return false;
 
         if (!$wasCreated) {
             $last = $order->provider_last_error_at;
             if ($last && $last->gt(now()->subSeconds(30))) {
                 return false;
             }
         }
 
         $serviceId = (int)$order->remote_service_id;
         $link      = (string)$order->link;
         $groupKey  = sha1($serviceId . '|' . $link);
+
+        $dedupeKey = 'tg:inspect:dispatch:' . $groupKey;
+        $dedupeTtl = 90;
+        if (!\Illuminate\Support\Facades\Cache::add($dedupeKey, 1, $dedupeTtl)) {
+            return false;
+        }
 
         $lockTtl = $wasCreated ? 90 : 45; // ✅ a bit longer, less spam
         $dispatchLock = Cache::lock("socpanel:validate-group-dispatch:{$groupKey}", $lockTtl);
```

(Use `Cache::add`; ensure `Illuminate\Support\Facades\Cache` is imported if not already.)

---

### C.2) Bounded throttle for inspect mode

**File:** `app/Services/Telegram/TelegramMtprotoPoolService.php`

- **waitForProxyThrottle**: return `bool` (true = slot acquired, false = gave up). For **inspect** mode use a shorter **maxWait** (e.g. 5–10s from config).
- **executeWithPool**: after `waitForProxyThrottleByMode`, if it returns false → release lock, return retryable fail so the job fails and Laravel retries with backoff.

**Config** in `config/telegram_mtproto.php`:

```php
'proxy_throttle_max_wait_inspect_sec' => env('TELEGRAM_MTPROTO_PROXY_THROTTLE_MAX_WAIT_INSPECT_SEC', 8),
```

**waitForProxyThrottle** (around line 1100):

```diff
-    private function waitForProxyThrottle(MtprotoTelegramAccount $account, string $mode): void
+    private function waitForProxyThrottle(MtprotoTelegramAccount $account, string $mode): bool
     {
         $throttleKey = $this->proxyThrottleKeyForMode($account, $mode);
         $seconds = $this->getProxyThrottleSeconds($throttleKey);
         $cacheKey = 'tg:proxy:throttle:' . $throttleKey;
 
-        $maxWait = 60;
+        $maxWait = $mode === self::MODE_INSPECT
+            ? (int) config('telegram_mtproto.proxy_throttle_max_wait_inspect_sec', 8)
+            : 60;
         $waited = 0;
         while ($waited < $maxWait) {
             if (Cache::add($cacheKey, 1, $seconds)) {
-                return;
+                return true;
             }
             $this->jitterSleepMs(150, 350);
             $waited += 0.25;
         }
+        return false;
     }
```

**waitForProxyThrottleByMode**:

```diff
-    private function waitForProxyThrottleByMode(MtprotoTelegramAccount $account, string $mode): void
+    private function waitForProxyThrottleByMode(MtprotoTelegramAccount $account, string $mode): bool
     {
-        $this->waitForProxyThrottle($account, $mode);
+        return $this->waitForProxyThrottle($account, $mode);
     }
```

**executeWithPool** (after line 535, throttle call):

```diff
-                // Throttle (keep as-is)
-                $this->waitForProxyThrottleByMode($account, $mode);
+                // Throttle: bounded for inspect so we don't block worker long
+                if (!$this->waitForProxyThrottleByMode($account, $mode)) {
+                    $accLock->release();
+                    return $this->failWithMeta(
+                        'MTPROTO_THROTTLE_SLOT_UNAVAILABLE',
+                        'Proxy throttle slot not acquired in time',
+                        $rid,
+                        $mode,
+                        $attempt + 1,
+                        $account,
+                        ['reason' => 'throttle_slot_timeout', 'retryable' => true]
+                    );
+                }
                 $this->recordProxyCallWindow($account, $mode);
```

---

### C.3) Inspect mode deadline config

**File:** `config/telegram_mtproto.php`

Add an optional shorter deadline for inspect so workers don’t hold the slot as long when many accounts are tried:

```php
'call_deadline_ms' => env('TELEGRAM_MTPROTO_CALL_DEADLINE_MS', 30000),
'call_deadline_inspect_ms' => env('TELEGRAM_MTPROTO_CALL_DEADLINE_INSPECT_MS', 0),
```

Use in **executeWithPool** (where `$deadlineMs` is set):

- If `call_deadline_inspect_ms` > 0 and `$mode === self::MODE_INSPECT`, use it; else use `call_deadline_ms`.

```diff
-        $deadlineMs  = (int) config('telegram_mtproto.call_deadline_ms', 30000);
+        $deadlineMs  = (int) (
+            ($mode === self::MODE_INSPECT && (int) config('telegram_mtproto.call_deadline_inspect_ms', 0) > 0)
+                ? config('telegram_mtproto.call_deadline_inspect_ms')
+                : config('telegram_mtproto.call_deadline_ms', 30000)
+        );
```

**Tradeoff:** Lower inspect deadline (e.g. 12–20s) reduces worker occupancy and backlog pressure but may increase NO_AVAILABLE_ACCOUNTS when many accounts are on cooldown/throttle. Prefer 15–20s for inspect if you see frequent deadline exceeded.

---

### C.4) Peer-db retry then skip + logging

**File:** `app/Services/Telegram/TelegramMtprotoPoolService.php`

- After the **one** fresh-runtime retry for peer-db, if the retry **fails** (any exception): catch it, log with **rid**, **account_id**, **proxy_key**, and **reason: peer_db_retry_failed**, then **do not rethrow** — set **$result = fail with retryable** so the loop **continues** to the next account (or eventually NO_AVAILABLE_ACCOUNTS).

Current block (simplified):

```php
if ($isPeerDb) {
    $this->factory->forgetRuntimeInstance($account);
    $madeline2 = $this->factory->makeForRuntime($account);
    ...
    $result = $callback($account, $madeline2);
} else {
    throw $e;
}
```

Change to:

```php
if ($isPeerDb) {
    $this->factory->forgetRuntimeInstance($account);
    $madeline2 = $this->factory->makeForRuntime($account);
    $this->revoltErrorHandlerSetBeforeStart();
    $madeline2->start();
    try {
        $result = $callback($account, $madeline2);
    } catch (\Throwable $e2) {
        Log::warning('MTProto peer-db retry failed, skipping account', [
            'rid' => $rid,
            'account_id' => $account->id,
            'proxy_key' => $proxyKey,
            'reason' => 'peer_db_retry_failed',
            'message' => substr($e2->getMessage(), 0, 200),
        ]);
        $result = $this->fail(
            'PEER_NOT_IN_DB',
            $e2->getMessage(),
            ['rid' => $rid, 'mode' => $mode, 'attempt' => $attempt + 1, 'account_id' => $account->id, 'proxy_key' => $proxyKey, 'reason' => 'peer_db_retry_failed', 'retryable' => true]
        );
    }
} else {
    throw $e;
}
```

Then the existing `$callbackOk` / `$willRetry` logic will see `retryable` and **continue** to the next account.

---

### C.5) Logging additions

- **executeWithPool**: when returning NO_AVAILABLE_ACCOUNTS (both “no account” and “all attempts exhausted”), add one **Log::info** with **rid**, **reason** (from meta), **mode**, **attempt**, **exclude_ids_count**, **exclude_proxy_keys_count** (you already have similar in some paths; ensure both early exit and loop exhaustion log).
- **Peer-db first occurrence**: optional **Log::info** when entering the peer-db branch (rid, account_id, proxy_key) so you can correlate with “peer_db_retry_failed” in one place.

---

### C.6) Proxy key strategy (rotating monoculture)

- **No code change required** if `proxy_mode` is **rotating**: keys are already per-account.  
- In **config/telegram_mtproto.php** add a documented key so production is explicit:

```php
'proxy_mode' => env('TELEGRAM_MTPROTO_PROXY_MODE', 'rotating'), // rotating | static
```

Use it in **proxyThrottleKey** instead of defaulting in code:

```php
$mode = config('telegram_mtproto.proxy_mode', 'rotating');
```

---

## Section D: Verification checklist

- **Horizon metrics (after changes)**  
  - **tg-inspect** queue: **wait time** (e.g. `redis:tg-inspect` in Horizon dashboard) should drop over 1–2 hours.  
  - **Pending jobs** count should stop growing and ideally decrease.  
  - **Throughput**: completed jobs per minute for tg-inspect; compare before/after.

- **Logs**  
  - **NO_AVAILABLE_ACCOUNTS**: every occurrence has **rid**, **reason** (db_query_empty, proxy_cooldown_all_dropped, all_attempts_exhausted, etc.), and (where applicable) **cooldown_remaining**, **cooldown_proxy_key**.  
  - **peer_db_retry_failed**: log line with rid, account_id, proxy_key; correlate with PEER_NOT_IN_DB in inspection result.  
  - **MTPROTO_THROTTLE_SLOT_UNAVAILABLE**: appears when inspect throttle is bounded and slot not acquired; job should retry with backoff.

- **Config**  
  - **telegram_mtproto.proxy_mode** = `rotating` in production.  
  - **proxy_throttle_max_wait_inspect_sec** = 8 (or 5–10).  
  - **call_deadline_inspect_ms** = 15000 or 20000 if you enable it.

- **Dedupe**  
  - Same (serviceId, link) should not get more than one job per 90s; check Redis for key `tg:inspect:dispatch:{sha1(serviceId|link)}` (or your prefix) and confirm TTL.

- **Horizon supervisor (recommendations)**  
  - **tg-inspect**: **processes**: prod 2–3, local 1–2; **timeout** 180–210; **tries** 1 (or 2 with backoff); **balance** simple.  
  - Keep **memory** 256 for MTProto.  
  - If backlog remains high, cap **maxProcesses** at 2 for tg-inspect until throttle and dedupe are in place, then increase gradually.
