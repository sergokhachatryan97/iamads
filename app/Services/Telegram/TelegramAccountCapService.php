<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Redis;

class TelegramAccountCapService
{
    /**
     * Try to consume one unit from daily cap. Returns true if consumed (under cap), false if cap reached.
     *
     * @param int $accountId
     * @param string $action
     * @param int|null $dailyCap If null, reads from config
     * @return bool True if consumed (under cap), false if cap reached
     */
    public function tryConsume(int $accountId, string $action, ?int $dailyCap = null): bool
    {
        $dailyCap = $dailyCap ?? $this->getDailyCap($action);
        $key = $this->getKey($accountId, $action);

        // Atomic INCR and check
        $pipeline = Redis::pipeline();
        $pipeline->incr($key);
        $pipeline->ttl($key);
        $results = $pipeline->execute();

        $currentCount = $results[0];
        $ttl = $results[1];

        // If key was just created (ttl = -1), set TTL to end of day
        if ($ttl === -1) {
            $secondsUntilMidnight = $this->secondsUntilMidnight();
            Redis::expire($key, $secondsUntilMidnight);
        }

        // Check if under cap
        if ($currentCount <= $dailyCap) {
            return true;
        }

        // Over cap: decrement back (rollback)
        Redis::decr($key);
        return false;
    }

    /**
     * Get current daily count for an account+action.
     *
     * @param int $accountId
     * @param string $action
     * @return int Current count
     */
    public function getCurrentCount(int $accountId, string $action): int
    {
        $key = $this->getKey($accountId, $action);
        $count = Redis::get($key);

        return $count ? (int) $count : 0;
    }

    /**
     * Get remaining capacity for an account+action today.
     *
     * @param int $accountId
     * @param string $action
     * @param int|null $dailyCap If null, reads from config
     * @return int Remaining capacity (0 if at cap)
     */
    public function getRemaining(int $accountId, string $action, ?int $dailyCap = null): int
    {
        $dailyCap = $dailyCap ?? $this->getDailyCap($action);
        $current = $this->getCurrentCount($accountId, $action);

        return max(0, $dailyCap - $current);
    }

    /**
     * Get Redis key for account+action daily cap.
     *
     * @param int $accountId
     * @param string $action
     * @return string
     */
    private function getKey(int $accountId, string $action): string
    {
        $date = now()->format('Ymd');
        return "tg:cap:{$action}:{$accountId}:{$date}";
    }

    /**
     * Calculate seconds until midnight (end of day).
     *
     * @return int
     */
    private function secondsUntilMidnight(): int
    {
        $now = now();
        $midnight = $now->copy()->endOfDay();
        return max(1, (int) $now->diffInSeconds($midnight, false));
    }

    /**
     * Get daily cap from config for an action.
     *
     * @param string $action
     * @return int
     */
    private function getDailyCap(string $action): int
    {
        $policy = config("telegram.action_policies.{$action}", []);
        return (int) ($policy['daily_cap'] ?? 10);
    }

    /**
     * Rollback (decrement) a consumed daily cap unit.
     * Safe to call multiple times (won't go below 0).
     *
     * @param int $accountId
     * @param string $action
     * @return void
     */
    public function rollbackConsume(int $accountId, string $action): void
    {
        $key = $this->getKey($accountId, $action);

        // Use Lua script for atomic decrement with floor at 0
        $lua = "
            local current = redis.call('GET', KEYS[1])
            if current and tonumber(current) > 0 then
                redis.call('DECR', KEYS[1])
            end
        ";

        try {
            Redis::eval($lua, 1, $key);
        } catch (\Throwable $e) {
            // Fallback: simple DECR (may go negative, but that's acceptable for rollback)
            Redis::decr($key);
        }
    }
}
