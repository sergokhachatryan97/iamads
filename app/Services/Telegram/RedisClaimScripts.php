<?php

namespace App\Services\Telegram;

/**
 * Redis Lua scripts for atomic account claim operations.
 *
 * These scripts ensure atomicity for cooldown, cap, and dedupe operations
 * in a single Redis call, eliminating race conditions and reducing latency.
 */
class RedisClaimScripts
{
    /**
     * Atomic account claim script.
     *
     * Performs in one atomic operation:
     * 1. Optional dedupe precheck (EXISTS dedupeKey)
     * 2. Cooldown claim (SET cooldownKey "1" NX EX cooldownTtl)
     * 3. Optional daily cap (INCR capKey, set EXPIRE if first, check limit)
     * 4. Dedupe mark (SET dedupeKey "1" NX EX dedupeTtl)
     * 5. Rollbacks on failures
     *
     * Keys:
     *   KEYS[1] = dedupeKey (optional, empty string if dedupe disabled)
     *   KEYS[2] = cooldownKey
     *   KEYS[3] = capKey (optional, empty string if cap disabled)
     *
     * Args:
     *   ARGV[1] = dedupeEnabled (1 or 0)
     *   ARGV[2] = dedupeTtlSec
     *   ARGV[3] = cooldownTtlSec
     *   ARGV[4] = capEnabled (1 or 0)
     *   ARGV[5] = capLimit
     *   ARGV[6] = capTtlSec (seconds until midnight)
     *
     * Returns array [status, reason, data]:
     *   [1, "ok"] - Successfully claimed
     *   [0, "dedupe"] - Already performed (dedupe key exists)
     *   [0, "cooldown", ttl] - In cooldown, returns remaining TTL
     *   [0, "cap"] - Daily cap reached
     *   [0, "dedupe_race"] - Dedupe mark failed (race condition)
     */
    public static function getClaimScript(): string
    {
        return <<<'LUA'
local dedupeKey   = KEYS[1]
local cooldownKey = KEYS[2]
local capKey      = KEYS[3]
local stateKey    = KEYS[4]

local dedupeEnabled   = tonumber(ARGV[1]) == 1
local dedupeTtlSec    = tonumber(ARGV[2])
local cooldownTtlSec  = tonumber(ARGV[3])
local capEnabled      = tonumber(ARGV[4]) == 1
local capLimit        = tonumber(ARGV[5])
local capTtlSec       = tonumber(ARGV[6])
local action          = tostring(ARGV[7] or '')

local isSubFlow = (action == 'subscribe' or action == 'unsubscribe')

-- Step 0: State gating for subscribe/unsubscribe (account+link)
-- rule:
--  - subscribe is allowed only if current state != 'subscribed'
--  - unsubscribe is allowed only if current state == 'subscribed'
if isSubFlow and stateKey ~= '' then
  local curState = redis.call('GET', stateKey) -- nil / 'subscribed' / 'unsubscribed'

  if action == 'subscribe' and curState == 'subscribed' then
    return {0, 'state_already_subscribed'}
  end

  if action == 'unsubscribe' and curState ~= 'subscribed' then
    return {0, 'state_not_subscribed'}
  end
end

-- Step 1: Optional dedupe precheck (for non-stateful actions like view/reaction)
if dedupeEnabled and dedupeKey ~= '' and (not isSubFlow) then
  if redis.call('EXISTS', dedupeKey) == 1 then
    return {0, 'dedupe'}
  end
end

-- Step 2: Cooldown claim (per-action)
local cooldownSet = redis.call('SET', cooldownKey, '1', 'EX', cooldownTtlSec, 'NX')
if cooldownSet == false then
  local ttl = redis.call('TTL', cooldownKey)
  if ttl < 0 then ttl = 0 end
  return {0, 'cooldown', ttl}
end

-- Step 3: Optional daily cap (subscribe/unsubscribe only)
if capEnabled and capKey ~= '' then
  local capCount = redis.call('INCR', capKey)
  -- safer TTL set:
  if capCount == 1 then
    redis.call('EXPIRE', capKey, capTtlSec)
  end

  if capCount > capLimit then
    redis.call('DEL', cooldownKey)
    redis.call('DECR', capKey)
    return {0, 'cap'}
  end
end

-- Step 4: Dedupe mark (race protection) for non-stateful actions
if dedupeEnabled and dedupeKey ~= '' and (not isSubFlow) then
  local ok = redis.call('SET', dedupeKey, '1', 'EX', dedupeTtlSec, 'NX')
  if ok == false then
    redis.call('DEL', cooldownKey)
    if capEnabled and capKey ~= '' then
      redis.call('DECR', capKey)
    end
    return {0, 'dedupe_race'}
  end
end

-- Step 5: State is NOT mutated at claim time.
-- State must be committed only after provider success via commitState().

return {1, 'ok'}
LUA;
    }

    /**
     * Rollback script for failed DB log insert.
     *
     * Keys:
     *   KEYS[1] = cooldownKey
     *   KEYS[2] = capKey (optional, empty string if not used)
     *   KEYS[3] = dedupeKey (optional, empty string if not used)
     *
     * Args:
     *   ARGV[1] = capUsed (1 if cap was consumed, 0 otherwise)
     *
     * Returns: number of keys deleted
     */
    public static function getRollbackScript(): string
    {
        return <<<'LUA'
local cooldownKey = KEYS[1]
local capKey = KEYS[2]
local dedupeKey = KEYS[3]
local capUsed = tonumber(ARGV[1]) == 1

local deleted = 0

-- Rollback cooldown
local cooldownDeleted = redis.call('DEL', cooldownKey)
deleted = deleted + cooldownDeleted

-- Rollback cap if it was consumed
if capUsed and capKey ~= '' then
    redis.call('DECR', capKey)
end

-- Optional: delete dedupe key if DB is source of truth
-- (comment out if you want to keep dedupe key even after DB log failure)
if dedupeKey ~= '' then
    local dedupeDeleted = redis.call('DEL', dedupeKey)
    deleted = deleted + dedupeDeleted
end

return deleted
LUA;
    }

    /**
     * Get script SHA1 hash for EVALSHA.
     * Scripts are loaded lazily on first use.
     */
    public static function getClaimScriptSha(): string
    {
        return sha1(self::getClaimScript());
    }

    public static function getRollbackScriptSha(): string
    {
        return sha1(self::getRollbackScript());
    }

    /**
     * Reserve script for two-phase claim system.
     *
     * This script only sets a short lock (120s TTL) to prevent concurrent assignment.
     * It does NOT consume cooldown or daily cap - those are consumed only on COMMIT.
     *
     * Keys:
     *   KEYS[1] = lockKey (tg:lock:{action}:{accountId})
     *   KEYS[2] = dedupeKey (optional, empty string if dedupe disabled)
     *   KEYS[3] = stateKey (for subscribe/unsubscribe state gating)
     *
     * Args:
     *   ARGV[1] = lockTtlSec (120 seconds)
     *   ARGV[2] = dedupeEnabled (1 or 0)
     *   ARGV[3] = action (subscribe/unsubscribe/etc)
     *
     * Returns array [status, reason]:
     *   [1, "ok"] - Successfully reserved
     *   [0, "locked"] - Already locked by another task
     *   [0, "dedupe"] - Already performed (dedupe key exists)
     *   [0, "state_already_subscribed"] - Subscribe not allowed (already subscribed)
     *   [0, "state_not_subscribed"] - Unsubscribe not allowed (not subscribed)
     */
    public static function getReserveScript(): string
    {
        return <<<'LUA'
local lockKey    = KEYS[1]
local dedupeKey  = KEYS[2]
local stateKey   = KEYS[3]

local lockTtlSec   = tonumber(ARGV[1])
local dedupeEnabled = tonumber(ARGV[2]) == 1
local action        = tostring(ARGV[3] or '')

local isSubFlow = (action == 'subscribe' or action == 'unsubscribe')

-- Step 1: State gating for subscribe/unsubscribe
if isSubFlow and stateKey ~= '' then
  local curState = redis.call('GET', stateKey)

  if action == 'subscribe' and curState == 'subscribed' then
    return {0, 'state_already_subscribed'}
  end

  if action == 'unsubscribe' and curState ~= 'subscribed' then
    return {0, 'state_not_subscribed'}
  end
end

-- Step 2: Dedupe precheck (for non-stateful actions)
if dedupeEnabled and dedupeKey ~= '' and (not isSubFlow) then
  if redis.call('EXISTS', dedupeKey) == 1 then
    return {0, 'dedupe'}
  end
end

-- Step 3: Set short lock (120s TTL) - this prevents concurrent assignment
local lockSet = redis.call('SET', lockKey, '1', 'EX', lockTtlSec, 'NX')
if lockSet == false then
  return {0, 'locked'}
end

-- Success: lock acquired, ready for task creation
return {1, 'ok'}
LUA;
    }

    /**
     * Commit script for two-phase claim system.
     *
     * This script is called ONLY when provider reports success (state=done, ok=true).
     * It consumes cooldown, daily cap, sets state, and releases the lock.
     *
     * Keys:
     *   KEYS[1] = lockKey (tg:lock:{action}:{accountId})
     *   KEYS[2] = cooldownKey (tg:cooldown:{action}:{accountId})
     *   KEYS[3] = capKey (tg:cap:{action}:{accountId}:{date})
     *   KEYS[4] = stateKey (tg:link_state:{linkHash}:{accountId})
     *   KEYS[5] = dedupeKey (optional, empty string if dedupe disabled)
     *
     * Args:
     *   ARGV[1] = cooldownTtlSec
     *   ARGV[2] = capEnabled (1 or 0)
     *   ARGV[3] = capLimit
     *   ARGV[4] = capTtlSec (seconds until midnight)
     *   ARGV[5] = stateTtlSec (90 days for subscribe/unsubscribe state)
     *   ARGV[6] = dedupeEnabled (1 or 0)
     *   ARGV[7] = dedupeTtlSec
     *   ARGV[8] = action (subscribe/unsubscribe/etc)
     *
     * Returns array [status, reason]:
     *   [1, "ok"] - Successfully committed
     *   [0, "no_lock"] - Lock not found (task was not reserved or already committed)
     *   [0, "cap_exceeded"] - Daily cap would be exceeded (should not happen if reserve worked)
     */
    public static function getCommitScript(): string
    {
        return <<<'LUA'
local lockKey     = KEYS[1]
local cooldownKey = KEYS[2]
local capKey      = KEYS[3]
local stateKey    = KEYS[4]
local dedupeKey   = KEYS[5]

local cooldownTtlSec = tonumber(ARGV[1])
local capEnabled     = tonumber(ARGV[2]) == 1
local capLimit       = tonumber(ARGV[3])
local capTtlSec      = tonumber(ARGV[4])
local stateTtlSec    = tonumber(ARGV[5])
local dedupeEnabled  = tonumber(ARGV[6]) == 1
local dedupeTtlSec   = tonumber(ARGV[7])
local action         = tostring(ARGV[8] or '')

local isSubFlow = (action == 'subscribe' or action == 'unsubscribe')

-- Step 1: Verify lock exists (task was reserved)
if redis.call('EXISTS', lockKey) == 0 then
  return {0, 'no_lock'}
end

-- Step 2: Set cooldown (per-action)
redis.call('SET', cooldownKey, '1', 'EX', cooldownTtlSec)

-- Step 3: Consume daily cap (subscribe/unsubscribe only)
if capEnabled and capKey ~= '' then
  local capCount = redis.call('INCR', capKey)
  if capCount == 1 then
    redis.call('EXPIRE', capKey, capTtlSec)
  end

  if capCount > capLimit then
    -- This should not happen if reserve worked, but handle gracefully
    redis.call('DEL', cooldownKey)
    redis.call('DECR', capKey)
    return {0, 'cap_exceeded'}
  end
end

-- Step 4: Commit state for subscribe/unsubscribe
if isSubFlow and stateKey ~= '' then
  if action == 'subscribe' then
    redis.call('SETEX', stateKey, stateTtlSec, 'subscribed')
  elseif action == 'unsubscribe' then
    redis.call('SETEX', stateKey, stateTtlSec, 'unsubscribed')
  end
end

-- Step 5: Set dedupe key for one-shot actions
if dedupeEnabled and dedupeKey ~= '' and (not isSubFlow) then
  redis.call('SETEX', dedupeKey, dedupeTtlSec, '1')
end

-- Step 6: Release lock
redis.call('DEL', lockKey)

return {1, 'ok'}
LUA;
    }

    public static function getReserveScriptSha(): string
    {
        return sha1(self::getReserveScript());
    }

    public static function getCommitScriptSha(): string
    {
        return sha1(self::getCommitScript());
    }
}
