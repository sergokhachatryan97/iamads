<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Central circuit breaker + kill switch for the queue/polling pipeline.
 *
 * PURPOSE
 * -------
 * The SMM pipeline (provider polling + MTProto inspection + Horizon workers)
 * is a CPU-heavy feedback loop: each poll dispatches many validate jobs, each
 * validate job spawns MadelineProto IPC workers, and each MadelineProto call
 * burns crypto/network. Under peak traffic this loop could saturate 16 cores
 * and cause runaway load (>85 observed in prod 2026-04-16).
 *
 * SystemGuard gives every dispatcher/poller a single, cheap, consistent way
 * to decide "should I do heavy work right now?" — without sprinkling ad-hoc
 * getloadavg() / env() checks across 20 jobs.
 *
 * USAGE
 * -----
 *   use App\Support\SystemGuard;
 *
 *   if (SystemGuard::shouldSkipHeavyWork('socpanel_poll')) {
 *       return; // guard logged the reason, just bail out
 *   }
 *
 *   // ...heavy work (MTProto call, provider API, etc.)
 *
 * SEMANTICS
 * ---------
 * - shouldSkipHeavyWork() returns true when EITHER the kill switch is on
 *   OR the 1-min load average is above the configured threshold.
 * - Logging is rate-limited to once per 60s per caller tag to avoid log spam
 *   during sustained overload.
 * - Safe to call from inside Horizon jobs, scheduled commands, controllers.
 * - Zero external dependencies beyond Cache (for rate-limited logging).
 *
 * WHY NOT A MIDDLEWARE
 * --------------------
 * Laravel job middleware runs AFTER the worker has already picked up and
 * unserialized the job — the CPU cost of that pickup is already paid. We want
 * to skip BEFORE dispatching so the work never enters the queue in the first
 * place. Callers check the guard at dispatch time (in pollers) AND at the top
 * of handle() (defense in depth for jobs already queued before pause).
 */
final class SystemGuard
{
    /**
     * Should the caller skip heavy/dispatching work right now?
     * Checks both the manual kill switch and the load-average circuit breaker.
     *
     * @param string $tag free-form tag used only for rate-limited log output
     *                    (e.g. 'socpanel_poll', 'tg_validate', 'memberpro_poll')
     */
    public static function shouldSkipHeavyWork(string $tag = 'generic'): bool
    {
        if (self::isSystemPaused()) {
            self::logOnce("guard_pause_{$tag}", 'SystemGuard: SYSTEM_PAUSE active, skipping heavy work', [
                'tag' => $tag,
            ]);

            return true;
        }

        if (self::isOverloaded()) {
            self::logOnce("guard_overload_{$tag}", 'SystemGuard: load too high, skipping heavy work', [
                'tag' => $tag,
                'load_1m' => self::currentLoad(),
                'threshold' => self::loadThreshold(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Manual kill switch. Flip SYSTEM_PAUSE=true in .env and reload Horizon,
     * OR set the cache key `system:pause` (so you don't need to restart).
     * The cache key takes precedence so operators can toggle pause without
     * a deploy.
     */
    public static function isSystemPaused(): bool
    {
        // Cache-driven switch wins — lets ops flip pause at runtime via
        // `php artisan system:pause` / `system:resume` without editing .env.
        $cacheFlag = Cache::get('system:pause');
        if ($cacheFlag !== null) {
            return (bool) $cacheFlag;
        }

        return (bool) config('system_guard.pause', false);
    }

    /**
     * Is the 1-min load average above the configured threshold?
     * On non-Linux hosts (or where sys_getloadavg is disabled), returns false
     * (fail-open) so local dev / CI don't trip the guard.
     */
    public static function isOverloaded(): bool
    {
        $load = self::currentLoad();
        if ($load === null) {
            return false;
        }

        return $load > self::loadThreshold();
    }

    /**
     * Current 1-min load average, or null if unavailable.
     */
    public static function currentLoad(): ?float
    {
        if (! function_exists('sys_getloadavg')) {
            return null;
        }

        $load = @sys_getloadavg();
        if (! is_array($load) || ! isset($load[0])) {
            return null;
        }

        return (float) $load[0];
    }

    public static function loadThreshold(): float
    {
        return (float) config('system_guard.load_threshold', 20.0);
    }

    /**
     * Claim a short-lived per-key cooldown slot. Returns true if the claim
     * succeeded (caller may proceed), false if the slot is still hot.
     *
     * Use this to implement per-account / per-endpoint rate limiting that
     * applies the same way across all worker processes (uses Cache::add
     * which is atomic at the cache-driver level).
     *
     * Example: prevent polling provider X more than once every 5 seconds:
     *
     *   if (! SystemGuard::claim("poll:adtag:account:{$id}", 5)) {
     *       return; // another worker polled recently
     *   }
     */
    public static function claim(string $key, int $ttlSeconds): bool
    {
        return Cache::add("guard:claim:{$key}", 1, $ttlSeconds);
    }

    /**
     * Rate-limited logging so sustained overload doesn't flood storage/logs/.
     */
    private static function logOnce(string $key, string $message, array $context = []): void
    {
        $lockKey = "guard:log:{$key}";
        if (Cache::add($lockKey, 1, 60)) {
            Log::warning($message, $context);
        }
    }
}
