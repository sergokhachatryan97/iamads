<?php

namespace App\Support\Performer;

use Illuminate\Support\Facades\Redis;

/**
 * Shared Redis-backed semaphore that caps total concurrent claim transactions
 * across ALL claim services (Telegram, YouTube, App).
 *
 * Each in-flight claim opens 1 MySQL connection, so this prevents the user
 * from exceeding `max_user_connections` even under burst load.
 *
 * Tune CONCURRENCY_LIMIT based on:
 *   max_user_connections - reserved for queues/cron/web/other = available for claims
 */
class ClaimConcurrencyLimiter
{
    /**
     * Total concurrent claims allowed across Telegram + YouTube + App.
     * Set well below max_user_connections to leave headroom for queues, cron, web.
     *
     * Example: max_user_connections=150 -> set to 100, leaving 50 for everything else.
     */
    public const CONCURRENCY_LIMIT = 100;

    /** Shared Redis key — single counter across all claim services. */
    public const SLOT_KEY = 'claim:global_inflight';

    /** Auto-expiry for the in-flight counter (safety net for crashed requests). */
    public const SLOT_TTL_SECONDS = 30;

    /**
     * Acquire a slot. Returns the slot key on success, or null if the cap is reached.
     */
    public static function acquire(): ?string
    {
        $lua = <<<'LUA'
local cur = tonumber(redis.call('GET', KEYS[1]) or '0')
local lim = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2])
if cur >= lim then
  return -1
end
local new = redis.call('INCR', KEYS[1])
redis.call('EXPIRE', KEYS[1], ttl)
return new
LUA;

        try {
            $result = (int) Redis::eval($lua, 1, self::SLOT_KEY, self::CONCURRENCY_LIMIT, self::SLOT_TTL_SECONDS);

            return $result < 0 ? null : self::SLOT_KEY;
        } catch (\Throwable) {
            // Fail open if Redis is unavailable
            return self::SLOT_KEY;
        }
    }

    public static function release(string $slotKey): void
    {
        try {
            Redis::decr($slotKey);
        } catch (\Throwable) {
        }
    }
}
