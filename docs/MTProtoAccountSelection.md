# MTProto Account Selection — How an Account Is Chosen

This document explains **how the system selects an MTProto Telegram account** when it needs to perform an API call (e.g. `getInfo`, `checkInvite`). The logic lives in `TelegramMtprotoPoolService::selectAvailableAccount()` and is used by `executeWithPool()`.

---

## 1. Where selection happens

- **Entry point:** Any call that uses the MTProto pool (e.g. `getInfoByUsername()`, `checkInvite()`) runs inside `executeWithPool($callback, $mode)`.
- **Mode:** Either `inspect` (light calls like getInfo) or `heavy` (heavier operations). Mode changes which accounts are eligible and how they are capped.
- **Selection:** On each attempt, the pool calls `selectAvailableAccount($excludeIds, $mode)` to get one account. If that account fails (lock not acquired, proxy cooldown, or API error), its ID is added to `$excludeIds` and the pool tries again, up to `max_accounts_to_try_per_call` (config, default 5).

So: **“getting an account” = one call to `selectAvailableAccount()`**, possibly repeated with exclusions until success or exhaustion.

---

## 2. Step-by-step: `selectAvailableAccount($excludeIds, $mode)`

### 2.1 Load candidates from DB

A batch of **candidate accounts** is loaded with:

**Base conditions (always):**

- `is_active = true`
- `disabled_at IS NULL`
- **Cooldown:** `cooldown_until IS NULL` OR `cooldown_until <= now()`  
  (account-level cooldown, e.g. after FLOOD_WAIT or errors)

**Mode-specific:**

- **`inspect`:** `is_inspect = true`
- **`heavy`:**  
  - `is_heavy = true`  
  - and (no daily window yet **or** window reset time passed **or** `daily_heavy_used < daily_heavy_cap`)

**Exclusions:**

- If `$excludeIds` is not empty, accounts with `id IN ($excludeIds)` are excluded (accounts already tried this run).

**Order and limit:**

- Order: `last_used_at IS NULL DESC`, then `last_used_at ASC`, then `id ASC`  
  → prefer never-used, then least recently used.
- Limit: `selection_batch_size` (config, default 20).

So the first step is: **up to N “eligible” accounts, preferring least recently used, excluding already-tried IDs.**

---

### 2.2 Drop accounts whose proxy is on cooldown

Each candidate has an associated **proxy** (or `no_proxy`). The proxy is identified by a **proxy key** (see §4).

- For each candidate, we ask: `proxyHealth->isOnCooldown(proxyKey)`.
- If the proxy is on cooldown (e.g. after STREAM_CLOSED, IPC error), the **account is removed** from the candidate list.
- If no candidates remain, the method returns `null` → “no available accounts”.

So: **only accounts whose proxy is not on cooldown** stay in the list.

---

### 2.3 Sort by proxy health score, then by last used

**Proxy health score** (`ProxyHealthService::score(proxyKey)`):

- If the proxy is on cooldown → score = `-999999` (already filtered above).
- Otherwise: `score = success*2 - error*5` over the health window (success/error counts in cache).
- Higher score = healthier proxy.

**Sort rules:**

1. **Primary:** by this score **descending** (best proxy first).
2. **Tie-break:** by `last_used_at` **ascending** (oldest use first).
3. **Final tie-break:** by `id` **ascending**.

So: **best proxy first, then least recently used.**

---

### 2.4 Pick one among equal top scores (randomise to spread load)

- All candidates are now ordered by (score, last_used_at, id).
- We take the **top score** and form the “equal score group”: all candidates with that score.
- If there is **more than one** in that group, we **shuffle** it so the same account is not always first.
- We then take: **first of (shuffled top group + rest)**.

So: **among the best proxies we randomise**, then fall back to the rest of the list; we return a **single** account (or `null` if the list was empty after proxy cooldown).

---

## 3. Summary flow (how “an account” is chosen)

```
1. DB: active, not disabled, not on account cooldown, mode-capable (inspect/heavy), not in excludeIds
   → order by (last_used_at null first, then asc, id asc), limit batch_size
2. Filter out accounts whose proxy is on cooldown (ProxyHealthService).
3. Sort by proxy health score (desc), then last_used_at (asc), then id (asc).
4. Among candidates with the best score, shuffle; then pick first of [shuffled best, rest].
5. Return that one account (or null).
```

So **selection = eligible DB candidates → drop by proxy cooldown → sort by health + usage → randomise among best → one account**.

---

## 4. Proxy key (how accounts are grouped by proxy)

An account is tied to a **proxy** (or no proxy). The same proxy can be used by several accounts.

- **Key:** `proxyThrottleKey($account)`:
  - If no `proxy_type` or `proxy_host` → `'no_proxy'`.
  - Else: `sha1(json_encode({ type, host, port, user, pass, secret }))` first 16 chars.
- **Usage:** This key is used for:
  - **Throttle:** one call per proxy every `proxy_throttle_sec` (or dynamic FLOOD_WAIT interval).
  - **Health:** success/error counts and cooldown are **per proxy key** (ProxyHealthService).
  - **Score:** selection uses this key to get `proxyHealth->score(proxyKey)` and `isOnCooldown(proxyKey)`.

So **“select account”** effectively picks an account whose **proxy** is not on cooldown and has the best health score, then breaks ties by usage and randomisation.

---

## 5. After selection: what happens before the API call

Once `selectAvailableAccount()` has returned an account, `executeWithPool()` does **not** call the API immediately. It:

1. **Locks the account** (`tg:mtproto:lock:{id}`) so the same account is not used by another job at the same time.
2. **Updates** `last_used_at = now()` for that account.
3. **Re-checks proxy cooldown** (in case it was set by another process); if on cooldown, releases lock, adds account to `$excludeIds`, and tries the next account.
4. **Waits for proxy throttle** (per-proxy rate limit, possibly dynamic after FLOOD_WAIT).
5. **Records the call** in the per-proxy “flood window” (for FLOOD_WAIT rate calculation).
6. **Starts** the MadelineProto API and runs the **callback** (the actual getInfo/checkInvite/etc.).

So **“getting the account”** is only the selection step; **using it** involves lock, throttle, and then the real call.

---

## 6. Config keys that affect selection

| Key | Meaning | Default |
|-----|---------|--------|
| `telegram_mtproto.selection_batch_size` | Max DB candidates per selection | 20 |
| `telegram_mtproto.max_accounts_to_try_per_call` | Max different accounts to try in one executeWithPool run | 5 |
| `telegram_mtproto.proxy_throttle_sec` | Min seconds between calls per proxy (if no dynamic rate) | 2 |
| `telegram_mtproto.proxy_health_ttl_seconds` | TTL for proxy success/error counts | 600 |

Proxy cooldown and health are stored in cache by `ProxyHealthService` (keys like `tg:proxy:health:{proxyKey}`, `tg:proxy:cooldown:{proxyKey}`).

---

## 7. Quick reference

- **Who selects?** `TelegramMtprotoPoolService::selectAvailableAccount($excludeIds, $mode)`.
- **Eligibility:** active, not disabled, not on account cooldown, mode (inspect/heavy), proxy not on cooldown.
- **Choice:** best proxy health score → then least recently used → randomise among top score.
- **Proxy key:** identifies the proxy (or `no_proxy`) for throttle, health, and cooldown; same key for all accounts using that proxy.

This is the full logic of how “select account” gets the account used for an MTProto call.
