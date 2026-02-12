<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Redis;

class TelegramAccountCooldownService
{
    /**
     * Try to claim cooldown for an account+action. Returns true if claimed, false if still in cooldown.
     *
     * @param int $accountId
     * @param string $action
     * @param int|null $cooldownSeconds If null, reads from config
     * @return bool True if claimed (not in cooldown), false if still in cooldown
     */
    public function tryClaim(int $accountId, string $action, ?int $cooldownSeconds = null): bool
    {
        $cooldownSeconds = $cooldownSeconds ?? $this->getCooldownSeconds($action);
        $key = $this->getKey($accountId, $action);

        // SET key "1" NX EX ttl
        // Returns true if key was set (not in cooldown), false if key already exists (in cooldown)
        $result = Redis::set($key, '1', 'EX', $cooldownSeconds, 'NX');

        return $result === true || $result === 'OK';
    }

    /**
     * Get remaining cooldown seconds for an account+action.
     *
     * @param int $accountId
     * @param string $action
     * @return int Remaining seconds, 0 if not in cooldown
     */
    public function remainingSeconds(int $accountId, string $action): int
    {
        $key = $this->getKey($accountId, $action);
        $ttl = Redis::ttl($key);

        return max(0, $ttl);
    }

    /**
     * Get the minimum remaining cooldown across multiple accounts for an action.
     * Useful for calculating reschedule delay.
     *
     * @param array<int> $accountIds
     * @param string $action
     * @return int Minimum remaining seconds, 0 if none in cooldown
     */
    public function minRemainingSeconds(array $accountIds, string $action): int
    {
        if (empty($accountIds)) {
            return 0;
        }

        $minRemaining = PHP_INT_MAX;
        foreach ($accountIds as $accountId) {
            $remaining = $this->remainingSeconds($accountId, $action);
            if ($remaining > 0 && $remaining < $minRemaining) {
                $minRemaining = $remaining;
            }
        }

        return $minRemaining === PHP_INT_MAX ? 0 : $minRemaining;
    }

    /**
     * Get Redis key for account+action cooldown.
     *
     * @param int $accountId
     * @param string $action
     * @return string
     */
    private function getKey(int $accountId, string $action): string
    {
        return "tg:cooldown:{$action}:{$accountId}";
    }

    /**
     * Get cooldown seconds from config for an action.
     *
     * @param string $action
     * @return int
     */
    private function getCooldownSeconds(string $action): int
    {
        $policy = config("telegram.action_policies.{$action}", []);
        return (int) ($policy['cooldown_seconds'] ?? 60);
    }

    /**
     * Get Redis key for global account cooldown (all actions).
     *
     * @param int $accountId
     * @return string
     */
    public function getGlobalKey(int $accountId): string
    {
        return "tg:cooldown:global:{$accountId}";
    }

    /**
     * Try to claim global cooldown for an account (all actions). Returns true if claimed, false if still in cooldown.
     *
     * @param int $accountId
     * @param int|null $cooldownSeconds If null, reads from config
     * @return bool True if claimed (not in cooldown), false if still in cooldown
     */
    public function tryClaimGlobal(int $accountId, ?int $cooldownSeconds = null): bool
    {
        $cooldownSeconds = $cooldownSeconds ?? (int) config('telegram.global_action_cooldown_seconds', 1800);
        $key = $this->getGlobalKey($accountId);

        // SET key "1" NX EX ttl
        // Returns true if key was set (not in cooldown), false if key already exists (in cooldown)
        $result = Redis::set($key, '1', 'EX', $cooldownSeconds, 'NX');

        return $result === true || $result === 'OK';
    }

    /**
     * Get remaining global cooldown seconds for an account.
     *
     * @param int $accountId
     * @return int Remaining seconds, 0 if not in cooldown
     */
    public function remainingGlobalSeconds(int $accountId): int
    {
        $key = $this->getGlobalKey($accountId);
        $ttl = Redis::ttl($key);

        return max(0, $ttl);
    }

    public function releaseGlobal(int $accountId): void
    {
        $key = $this->getGlobalKey($accountId);
        Redis::del($key);
    }

}
