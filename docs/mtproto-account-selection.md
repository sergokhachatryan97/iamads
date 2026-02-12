# MTProto Account Selection and Throttling

This document describes how the MTProto pool selects an account, applies proxy throttle/health/cooldown, and handles lock contention and same-proxy setups. It reflects the logic in `TelegramMtprotoPoolService` and `ProxyHealthService`.

---

## 1. Selection step-by-step

### 1.1 DB filters

The pool loads **candidate accounts** from the DB with:

- `is_active = true`, `disabled_at IS NULL`
- **Account cooldown:** `cooldown_until IS NULL` OR `cooldown_until <= now()`
- **Mode:**
  - **inspect:** `is_inspect = true`
  - **heavy:** `is_heavy = true` and (no daily window yet, or window reset time passed, or `daily_heavy_used < daily_heavy_cap`)
- **Exclusions:** `id NOT IN ($excludeIds)` (accounts already tried this run)
- **Order:** `last_used_at IS NULL DESC`, then `last_used_at ASC`, then `id ASC`
- **Limit:** `selection_batch_size` (default 20)

### 1.2 Proxy cooldown filter

Each candidate has a **base proxy key** (see §3). If `ProxyHealthService::isOnCooldown(baseProxyKey)` is true, the account is **removed** from the list. Health/cooldown are **per proxy** (no mode). If no candidates remain, selection returns `null`.

### 1.3 Sorting: monoculture vs multi-proxy

- **Unique proxy keys** in the candidate list are counted.
- **Monoculture (single proxy):** all candidates share the same proxy key.  
  Sort by: **penalty last** (non-penalized first), then **last_used_at ASC**, then **id ASC**.  
  So: LRU + penalty-aware, no proxy score.
- **Multi-proxy:**  
  **Effective score** = proxy health score − penalty weight if account is penalized.  
  Sort by: **effective score DESC**, then **last_used_at ASC**, then **id ASC**.

### 1.4 Top-K random pick

After sorting, the pool takes the **top K** candidates (config `selection_top_k`, default 7), **shuffles** them, and returns the **first**. So one account is chosen randomly among the best K to reduce lock contention and avoid always picking the same account.

### 1.5 Summary flow

```
DB query (active, cooldown, mode, excludeIds, order LRU, limit batch_size)
  → drop accounts whose proxy is on cooldown (base key)
  → if uniqueProxyKeys === 1: sort by (penalty last, last_used_at asc, id)
  → else: sort by (effective score desc, last_used_at asc, id)
  → take top K, shuffle, return first
```

---

## 2. Penalty mechanism (lock contention)

- **When:** `executeWithPool()` fails to **acquire the account lock** for the chosen account.
- **Action:** Set a **selection-only** penalty in cache:  
  `tg:acct:penalty:{accountId}` = 1, TTL = `lock_penalty_ttl_seconds` (default 20, clamped 10–30).
- **No DB writes.** Only cache.
- **Effect:** In the **next** selection, that account’s **effective score** is reduced (or in monoculture it is sorted after non-penalized). So other accounts get a chance; the same account is not retried immediately in a tight loop.
- **Result:** Under lock contention, subsequent attempts try different accounts (or different order), reducing deadline-exceeded and improving distribution.

---

## 3. Proxy key normalization and throttle keys

### 3.1 Base proxy key (health / cooldown)

- **Purpose:** One key per “logical” proxy so identical settings always map to the same key.
- **Normalization** (before hashing):
  - Trim string fields: type, host, user, pass, secret.
  - Cast port to int (empty/missing → null).
  - Empty string after trim → null.
  - If `proxy_type` or `proxy_host` missing/empty → key is **no_proxy**.
- **Key:** `no_proxy` or `substr(sha1(json_encode(normalized)), 0, 16)`.
- **Used for:** `ProxyHealthService` (cooldown, score, markSuccess, markError). **No mode** in this key.

### 3.2 Throttle key by mode

- **Purpose:** Separate throttle (and FLOOD_WAIT window/rate) so **inspect** and **heavy** do not starve each other.
- **Key:** `baseProxyKey . ':' . mode` (e.g. `abc123:inspect`, `abc123:heavy`).
- **Used for:**
  - Throttle slot: `tg:proxy:throttle:{baseProxyKey}:{mode}`
  - Flood window: `tg:proxy:floodwindow:{baseProxyKey}:{mode}`
  - Flood rate: `tg:proxy:floodrate:{baseProxyKey}:{mode}`

So: **health/cooldown = per proxy (base key); throttle/window/rate = per proxy + mode.**

---

## 4. Same-proxy monoculture handling

When **all** candidates share the same proxy key:

- Proxy **score** is the same for everyone, so scoring does not help.
- **Fallback:** account-level fairness:
  - Sort by **penalty** (non-penalized first), then **last_used_at ASC**, then **id ASC**.
  - Then **top-K random** as above.
- **Goal:** Fair rotation among 2–3 (or more) accounts on the same proxy and less lock contention.

---

## 5. Cache keys reference

| Key pattern | TTL / usage | Purpose |
|-------------|-------------|---------|
| `tg:acct:penalty:{id}` | 10–30 s (config) | Lock-failure penalty; selection-only |
| `tg:mtproto:lock:{id}` | lock_ttl_seconds | Per-account execution lock |
| `tg:proxy:health:{baseKey}` | proxy_health_ttl_seconds | Success/error counts (no mode) |
| `tg:proxy:cooldown:{baseKey}` | set on error | Proxy cooldown (no mode) |
| `tg:proxy:throttle:{baseKey}:{mode}` | throttle_sec or dynamic | One slot per call per proxy+mode |
| `tg:proxy:floodwindow:{baseKey}:{mode}` | 300 s | Window for FLOOD_WAIT rate (N calls in T s) |
| `tg:proxy:floodrate:{baseKey}:{mode}` | 3600 s | Learned interval (T+X)/N per mode |

---

## 6. Config keys

| Key | Meaning | Default |
|-----|---------|--------|
| `telegram_mtproto.selection_batch_size` | Max DB candidates per selection | 20 |
| `telegram_mtproto.selection_top_k` | Take top K, then shuffle and pick one | 7 |
| `telegram_mtproto.selection_penalty_weight` | Subtract from score when account is penalized | 1000 |
| `telegram_mtproto.lock_penalty_ttl_seconds` | Penalty cache TTL (10–30 clamped) | 20 |
| `telegram_mtproto.proxy_throttle_sec` | Default min seconds between calls per proxy+mode | 2 |
| `telegram_mtproto.max_accounts_to_try_per_call` | Max accounts to try in one executeWithPool run | 8 |
| `telegram_mtproto.call_deadline_ms` | Max time for one executeWithPool run | 30000 |
| `telegram_mtproto.proxy_health_ttl_seconds` | TTL for proxy health success/error | 600 |

---

## 7. Fail-fast: deadline and throttle

Before **waiting** for the proxy throttle slot, the pool checks:

- Remaining time ≈ `deadlineMs - (throttleSec + 1) * 1000`.
- If the call would already be past that remaining window (`deadlineExceeded(startedAtMs, deadlineMs - buffer)`), it **skips** waiting for throttle, releases the account lock, and **tries another account** (same excludeIds/penalty rules).
- **Result:** Fewer `MTPROTO_DEADLINE_EXCEEDED` when throttle wait would eat the whole deadline.

---

## 8. Example scenarios

### 8.1 Heavy: 3 accounts, same proxy

- All 3 have the same base proxy key.
- Monoculture path: sort by penalty then LRU.
- Top-K (e.g. 7) includes all 3; shuffle → any of the 3 can be chosen.
- Throttle key is `baseKey:heavy`, so heavy traffic does not share throttle with inspect.
- Lock penalty: if one account is locked, it gets a short penalty and the next selection prefers the other two.

### 8.2 Inspect load vs heavy

- Inspect uses `baseKey:inspect`, heavy uses `baseKey:heavy`.
- Throttle/flood window/rate are **per mode**, so inspect traffic does not starve heavy (and vice versa).
- Health/cooldown stay per proxy (base key), so a broken proxy still affects both modes.

### 8.3 Lock contention

- Many jobs run; often lock fails for the chosen account.
- On each lock failure, that account gets `tg:acct:penalty:{id}` for 10–30 s.
- Next selection: that account has lower effective score (or in monoculture is sorted after others), so other accounts are tried.
- Top-K random spreads which account is tried first among the best K, further reducing contention on a single account.

---

## 9. Acceptance criteria (mapping)

1. **Identical proxy settings → same proxyKey**  
   Implemented via `normalizeProxySettings()` + `proxyThrottleKey()`; covered by unit tests (identical settings, normalized whitespace, different port, no_proxy).

2. **Lock contention → penalty improves distribution**  
   Penalty on lock failure; selection ranks penalized accounts later (or subtracts penalty weight).

3. **2–3 heavy accounts, same proxy → fair selection**  
   Monoculture path: LRU + penalty, then top-K random.

4. **Inspect does not starve heavy**  
   Throttle (and flood window/rate) keys include mode (`:inspect` / `:heavy`).

5. **Fewer MTPROTO_DEADLINE_EXCEEDED**  
   Fail-fast: skip throttle wait when remaining time would exceed deadline; try another account instead.
