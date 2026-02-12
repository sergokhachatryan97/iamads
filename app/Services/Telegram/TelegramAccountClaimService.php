<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Service for atomic account claim operations using Redis Lua scripts.
 *
 * This service wraps RedisClaimScripts and provides a clean interface
 * for claiming accounts with cooldown, cap, and dedupe checks in one atomic operation.
 */
class TelegramAccountClaimService
{
    private RedisClaimScripts $scripts;
    private ?string $claimScriptSha = null;
    private ?string $rollbackScriptSha = null;

    public function __construct()
    {
        $this->scripts = new RedisClaimScripts();
    }

    /**
     * Try to claim an account for an action.
     *
     * @param int $accountId
     * @param string $action
     * @param string $linkHash
     * @param bool $dedupePerLink
     * @param bool $needsCap Whether cap should be checked (subscribe/unsubscribe only)
     * @return array{success: bool, reason: string, cooldownTtl?: int}
     */
    public function tryClaim(
        int $accountId,
        string $action,
        string $linkHash,
        bool $dedupePerLink,
        bool $needsCap
    ): array {
        // Build keys
        $dedupeKey = $dedupePerLink ? $this->getDedupeKey($accountId, $action, $linkHash) : '';
        $cooldownKey = $this->getCooldownKey($accountId, $action); // Per-action cooldown
        $capKey = $needsCap ? $this->getCapKey($accountId, $action) : '';

        // Get TTLs from config
        $dedupeTtlSec = $this->getDedupeTtl($action);
        $cooldownTtlSec = $this->getCooldownTtl($action); // Per-action TTL
        $capLimit = $needsCap ? $this->getCapLimit($action) : 0;
        $capTtlSec = $this->secondsUntilMidnight();

        // Build args (action is ARGV[7])
        $args = [
            $dedupePerLink ? '1' : '0',
            (string) $dedupeTtlSec,
            (string) $cooldownTtlSec,
            $needsCap ? '1' : '0',
            (string) $capLimit,
            (string) $capTtlSec,
            $action, // ARGV[7] - pass action to Lua script
        ];

        $stateKey = $this->getStateKey($accountId, $linkHash);

        $keys = [$dedupeKey, $cooldownKey, $capKey, $stateKey];
        $result = $this->evalClaimScript($keys, $args);

        // Parse result: [status, reason, optional_data]
        $status = (int) ($result[0] ?? 0);
        $reason = (string) ($result[1] ?? 'unknown');

        if ($status === 1 && $reason === 'ok') {
            return ['success' => true, 'reason' => 'ok'];
        }

        // Failure cases
        $response = ['success' => false, 'reason' => $reason];
        if ($reason === 'cooldown' && isset($result[2])) {
            $response['cooldownTtl'] = (int) $result[2];
        }

        return $response;
    }

    /**
     * Rollback a claim (e.g., if DB log insert fails).
     *
     * @param int $accountId
     * @param string $action
     * @param string $linkHash
     * @param bool $dedupePerLink
     * @param bool $capUsed Whether cap was consumed
     * @return void
     */
    public function rollbackClaim(
        int $accountId,
        string $action,
        string $linkHash,
        bool $dedupePerLink,
        bool $capUsed
    ): void {
        $cooldownKey = $this->getCooldownKey($accountId, $action); // Per-action cooldown
        $capKey = $capUsed ? $this->getCapKey($accountId, $action) : '';
        $dedupeKey = $dedupePerLink ? $this->getDedupeKey($accountId, $action, $linkHash) : '';

        // Always pass 3 keys (empty string if not used)
        $keys = [$cooldownKey, $capKey, $dedupeKey];
        $args = [$capUsed ? '1' : '0'];

        $this->evalRollbackScript($keys, $args);
    }

    /**
     * Get Redis key for dedupe check.
     */
    private function getDedupeKey(int $accountId, string $action, string $linkHash): string
    {
        return "tg:dedupe:{$action}:{$linkHash}:{$accountId}";
    }

    /**
     * Get Redis key for per-action cooldown.
     */
    private function getCooldownKey(int $accountId, string $action): string
    {
        return "tg:cooldown:{$action}:{$accountId}";
    }

    /**
     * Get Redis key for daily cap.
     */
    private function getCapKey(int $accountId, string $action): string
    {
        $date = now()->format('Ymd');
        return "tg:cap:{$action}:{$accountId}:{$date}";
    }

    /**
     * Get dedupe TTL from config (default 30 days).
     */
    private function getDedupeTtl(string $action): int
    {
        // Default 30 days, can be overridden per action if needed
        return 30 * 86400; // 2,592,000 seconds
    }

    /**
     * Get cooldown TTL per action from config.
     */
    private function getCooldownTtl(string $action): int
    {
        $cooldowns = config('telegram.action_cooldowns', []);
        if (isset($cooldowns[$action])) {
            return (int) $cooldowns[$action];
        }
        // Fallback to global cooldown
        return (int) config('telegram.global_action_cooldown_seconds', 1800);
    }

    /**
     * Get daily cap limit from config.
     */
    private function getCapLimit(string $action): int
    {
        $policy = config("telegram.action_policies.{$action}", []);
        return (int) ($policy['daily_cap'] ?? 10);
    }

    private function getStateKey(int $accountId, string $linkHash): string
    {
        return "tg:link_state:{$linkHash}:{$accountId}";
    }

    /**
     * Calculate seconds until midnight.
     */
    private function secondsUntilMidnight(): int
    {
        $now = now();
        $midnight = $now->copy()->endOfDay();
        return max(1, (int) $now->diffInSeconds($midnight, false));
    }

    /**
     * Execute claim script with EVALSHA fallback to EVAL.
     */
    private function evalClaimScript(array $keys, array $args): array
    {
        if ($this->claimScriptSha === null) {
            $this->claimScriptSha = RedisClaimScripts::getClaimScriptSha();
        }

        // Build args array: number of keys, then keys, then args
        $numKeys = count($keys);
        $evalArgs = array_merge($keys, $args);

        try {
            // Try EVALSHA first (faster)
            $result = Redis::command('EVALSHA', array_merge([$this->claimScriptSha, $numKeys], $evalArgs));
            return is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            // Script not loaded, fall back to EVAL
            if (str_contains($e->getMessage(), 'NOSCRIPT') || str_contains($e->getMessage(), 'No matching script')) {
                $script = RedisClaimScripts::getClaimScript();
                $result = Redis::command('EVAL', array_merge([$script, $numKeys], $evalArgs));
                return is_array($result) ? $result : [];
            }
            throw $e;
        }
    }

    /**
     * Execute rollback script with EVALSHA fallback to EVAL.
     */
    private function evalRollbackScript(array $keys, array $args): int
    {
        if ($this->rollbackScriptSha === null) {
            $this->rollbackScriptSha = RedisClaimScripts::getRollbackScriptSha();
        }

        $numKeys = count($keys);
        $evalArgs = array_merge($keys, $args);

        try {
            $result = Redis::command('EVALSHA', array_merge([$this->rollbackScriptSha, $numKeys], $evalArgs));
            return is_numeric($result) ? (int) $result : 0;
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'NOSCRIPT') || str_contains($e->getMessage(), 'No matching script')) {
                $script = RedisClaimScripts::getRollbackScript();
                $result = Redis::command('EVAL', array_merge([$script, $numKeys], $evalArgs));
                return is_numeric($result) ? (int) $result : 0;
            }
            throw $e;
        }
    }

    /**
     * Commit state after provider success.
     * Sets stateKey with TTL (90 days) to avoid permanent stale state.
     * 
     * NOTE: For new two-phase system, use commitClaim() instead.
     * This method is kept for backward compatibility.
     */
    public function commitState(int $accountId, string $action, string $linkHash): void
    {
        if (!in_array($action, ['subscribe', 'unsubscribe'], true)) return;

        $stateKey = $this->getStateKey($accountId, $linkHash);
        $stateTtl = 90 * 86400; // 90 days

        if ($action === 'subscribe') {
            Redis::setex($stateKey, $stateTtl, 'subscribed');
        } else {
            Redis::setex($stateKey, $stateTtl, 'unsubscribed');
        }
    }

    /**
     * Reserve an account for a task (two-phase claim: RESERVE phase).
     * 
     * This only sets a short lock (120s TTL) to prevent concurrent assignment.
     * Does NOT consume cooldown or daily cap - those are consumed only on COMMIT.
     *
     * @param int $accountId
     * @param string $action
     * @param string $linkHash
     * @param bool $dedupePerLink
     * @return array{success: bool, reason: string}
     */
    public function reserveClaim(
        int $accountId,
        string $action,
        string $linkHash,
        bool $dedupePerLink
    ): array {
        $lockKey = $this->getLockKey($accountId, $action);
        $dedupeKey = $dedupePerLink ? $this->getDedupeKey($accountId, $action, $linkHash) : '';
        $stateKey = $this->getStateKey($accountId, $linkHash);

        $lockTtlSec = 120; // 2 minutes lock TTL

        $keys = [$lockKey, $dedupeKey, $stateKey];
        $args = [
            (string) $lockTtlSec,
            $dedupePerLink ? '1' : '0',
            $action,
        ];

        $result = $this->evalReserveScript($keys, $args);

        $status = (int) ($result[0] ?? 0);
        $reason = (string) ($result[1] ?? 'unknown');

        if ($status === 1 && $reason === 'ok') {
            return ['success' => true, 'reason' => 'ok'];
        }

        return ['success' => false, 'reason' => $reason];
    }

    /**
     * Commit a reserved claim (two-phase claim: COMMIT phase).
     * 
     * This is called ONLY when provider reports success (state=done, ok=true).
     * Consumes cooldown, daily cap, sets state, and releases the lock.
     *
     * @param int $accountId
     * @param string $action
     * @param string $linkHash
     * @param bool $dedupePerLink
     * @param bool $needsCap
     * @return array{success: bool, reason: string}
     */
    public function commitClaim(
        int $accountId,
        string $action,
        string $linkHash,
        bool $dedupePerLink,
        bool $needsCap
    ): array {
        $lockKey = $this->getLockKey($accountId, $action);
        $cooldownKey = $this->getCooldownKey($accountId, $action);
        $capKey = $needsCap ? $this->getCapKey($accountId, $action) : '';
        $stateKey = $this->getStateKey($accountId, $linkHash);
        $dedupeKey = $dedupePerLink ? $this->getDedupeKey($accountId, $action, $linkHash) : '';

        $cooldownTtlSec = $this->getCooldownTtl($action);
        $capLimit = $needsCap ? $this->getCapLimit($action) : 0;
        $capTtlSec = $this->secondsUntilMidnight();
        $stateTtlSec = 90 * 86400; // 90 days
        $dedupeTtlSec = $this->getDedupeTtl($action);

        $keys = [$lockKey, $cooldownKey, $capKey, $stateKey, $dedupeKey];
        $args = [
            (string) $cooldownTtlSec,
            $needsCap ? '1' : '0',
            (string) $capLimit,
            (string) $capTtlSec,
            (string) $stateTtlSec,
            $dedupePerLink ? '1' : '0',
            (string) $dedupeTtlSec,
            $action,
        ];

        $result = $this->evalCommitScript($keys, $args);

        $status = (int) ($result[0] ?? 0);
        $reason = (string) ($result[1] ?? 'unknown');

        if ($status === 1 && $reason === 'ok') {
            return ['success' => true, 'reason' => 'ok'];
        }

        return ['success' => false, 'reason' => $reason];
    }

    /**
     * Rollback a reserved claim (release lock only).
     * 
     * This is called when provider reports failure or task expires.
     * Only releases the lock - does NOT rollback cooldown/cap (they weren't consumed yet).
     *
     * @param int $accountId
     * @param string $action
     * @return void
     */
    public function rollbackReserve(int $accountId, string $action): void
    {
        $lockKey = $this->getLockKey($accountId, $action);
        Redis::del($lockKey);
    }

    /**
     * Get Redis key for reservation lock.
     */
    private function getLockKey(int $accountId, string $action): string
    {
        return "tg:lock:{$action}:{$accountId}";
    }

    /**
     * Execute reserve script with EVALSHA fallback to EVAL.
     */
    private function evalReserveScript(array $keys, array $args): array
    {
        static $scriptSha = null;

        if ($scriptSha === null) {
            $scriptSha = RedisClaimScripts::getReserveScriptSha();
        }

        $numKeys = count($keys);
        $evalArgs = array_merge($keys, $args);

        try {
            $result = Redis::command('EVALSHA', array_merge([$scriptSha, $numKeys], $evalArgs));
            return is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'NOSCRIPT') || str_contains($e->getMessage(), 'No matching script')) {
                $script = RedisClaimScripts::getReserveScript();
                $result = Redis::command('EVAL', array_merge([$script, $numKeys], $evalArgs));
                return is_array($result) ? $result : [];
            }
            throw $e;
        }
    }

    /**
     * Execute commit script with EVALSHA fallback to EVAL.
     */
    private function evalCommitScript(array $keys, array $args): array
    {
        static $scriptSha = null;

        if ($scriptSha === null) {
            $scriptSha = RedisClaimScripts::getCommitScriptSha();
        }

        $numKeys = count($keys);
        $evalArgs = array_merge($keys, $args);

        try {
            $result = Redis::command('EVALSHA', array_merge([$scriptSha, $numKeys], $evalArgs));
            return is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'NOSCRIPT') || str_contains($e->getMessage(), 'No matching script')) {
                $script = RedisClaimScripts::getCommitScript();
                $result = Redis::command('EVAL', array_merge([$script, $numKeys], $evalArgs));
                return is_array($result) ? $result : [];
            }
            throw $e;
        }
    }

}
