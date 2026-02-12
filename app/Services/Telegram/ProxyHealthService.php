<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProxyHealthService
{
    private const HEALTH_KEY_PREFIX    = 'tg:proxy:health:';
    private const COOLDOWN_KEY_PREFIX  = 'tg:proxy:cooldown:';
    private const HEALTH_LOCK_PREFIX   = 'tg:proxy:health:lock:';

    private const HEALTH_TTL_SECONDS   = 600;      // 10 min window-ish
    private const HEALTH_LOCK_SECONDS  = 3;        // prevent race updates
    private const SCORE_ON_COOLDOWN    = -999999;

    private const NO_PROXY_KEY         = 'no_proxy';

    /** Keys that represent no-proxy (per-account or legacy); never apply global cooldown to all. */
    public static function isNoProxyKey(string $proxyKey): bool
    {
        return $proxyKey === self::NO_PROXY_KEY || str_starts_with($proxyKey, 'no_proxy:');
    }

    /** Infra error codes that trigger cooldown escalation */
    private const INFRA_CODES = [
        'IPC_UNAVAILABLE',
        'STREAM_CLOSED',
        'STREAM_NOT_WRITABLE',
        'BROKEN_PIPE',
        'STREAMISH',
    ];

    public function isOnCooldown(string $proxyKey): bool
    {
        $key = self::COOLDOWN_KEY_PREFIX . $proxyKey;
        $v = Cache::get($key);

        if (!is_array($v) || !isset($v['until'])) {
            return false;
        }

        return (int) $v['until'] > time();
    }

    public function cooldownRemaining(string $proxyKey): int
    {
        $key = self::COOLDOWN_KEY_PREFIX . $proxyKey;
        $v = Cache::get($key);

        if (!is_array($v) || !isset($v['until'])) {
            return 0;
        }

        $remaining = (int) $v['until'] - time();
        return $remaining > 0 ? $remaining : 0;
    }

    public function getHealth(string $proxyKey): array
    {
        $healthKey = self::HEALTH_KEY_PREFIX . $proxyKey;
        $data = Cache::get($healthKey);

        return is_array($data) ? $data : $this->defaultHealth();
    }

    public function markSuccess(string $proxyKey): void
    {
        $healthKey = self::HEALTH_KEY_PREFIX . $proxyKey;
        $ttl = $this->healthTtl();

        $this->withHealthLock($proxyKey, function () use ($healthKey, $ttl) {
            $data = Cache::get($healthKey);
            if (!is_array($data)) {
                $data = $this->defaultHealth();
            }

            $data['success'] = (int) ($data['success'] ?? 0) + 1;
            $data['last_success_at'] = now()->toIso8601String();

            // ✅ decay errors so "rolling window" behaves sanely
            $currentErr = (int) ($data['error'] ?? 0);
            $data['error'] = max(0, $currentErr - 1);

            Cache::put($healthKey, $data, $ttl);
        });
    }

    public function markError(string $proxyKey, string $errorCode): void
    {
        $errorCode = $this->normalizeCode($errorCode);

        $healthKey = self::HEALTH_KEY_PREFIX . $proxyKey;
        $cooldownKey = self::COOLDOWN_KEY_PREFIX . $proxyKey;
        $ttl = $this->healthTtl();

        $cooldownSec = null;
        $errorCountAfter = null;

        $this->withHealthLock($proxyKey, function () use (
            $proxyKey,
            $errorCode,
            $healthKey,
            $ttl,
            &$cooldownSec,
            &$errorCountAfter
        ) {
            $data = Cache::get($healthKey);
            if (!is_array($data)) {
                $data = $this->defaultHealth();
            }

            $data['error'] = (int) ($data['error'] ?? 0) + 1;
            $data['last_error_code'] = $errorCode;
            $data['last_error_at'] = now()->toIso8601String();

            Cache::put($healthKey, $data, $ttl);

            $isInfra   = in_array($errorCode, self::INFRA_CODES, true);
            $isNoProxy = self::isNoProxyKey($proxyKey);

            $errorCountAfter = (int) $data['error'];

            if ($isInfra) {
                if ($isNoProxy) {
                    $cooldownSec = 60;
                } else {
                    // ✅ clean escalation
                    if ($errorCountAfter >= 6) {
                        $cooldownSec = 900;   // 15 min
                    } elseif ($errorCountAfter >= 3) {
                        $cooldownSec = 300;   // 5 min
                    } else {
                        $cooldownSec = 120;   // 2 min
                    }

                    // hard cap (just in case later you change values)
                    $cooldownSec = min($cooldownSec, 1800);
                }
            } elseif ($errorCode === 'CANCELLED') {
                $cooldownSec = 60;
            }
        });

        // If no cooldown needed, stop here.
        if (!$cooldownSec) {
            return;
        }

        $until = time() + $cooldownSec;

        Cache::put($cooldownKey, [
            'until'  => $until,
            'reason' => $errorCode,
        ], $cooldownSec);

        Log::warning('MTProto proxy cooldown set', [
            'proxy_key' => $proxyKey,
            'seconds'   => $cooldownSec,
            'reason'    => $errorCode,
            'error_count' => $errorCountAfter,
        ]);
    }

    public function score(string $proxyKey): int
    {
        if ($this->isOnCooldown($proxyKey)) {
            return self::SCORE_ON_COOLDOWN;
        }

        $healthKey = self::HEALTH_KEY_PREFIX . $proxyKey;
        $data = Cache::get($healthKey);
        if (!is_array($data)) {
            return 0;
        }

        $success = (int) ($data['success'] ?? 0);
        $error   = (int) ($data['error'] ?? 0);

        return $success * 2 - $error * 5;
    }

    private function withHealthLock(string $proxyKey, callable $fn): void
    {
        $lockKey = self::HEALTH_LOCK_PREFIX . $proxyKey;
        $lockSec = (int) config('telegram_mtproto.proxy_health_lock_seconds', self::HEALTH_LOCK_SECONDS);

        $lock = Cache::lock($lockKey, max(1, $lockSec));
        if (!$lock->get()) {
            // If lock busy, we can skip to avoid blocking hot path
            return;
        }

        try {
            $fn();
        } finally {
            optional($lock)->release();
        }
    }

    private function healthTtl(): int
    {
        $ttl = (int) config('telegram_mtproto.proxy_health_ttl_seconds', self::HEALTH_TTL_SECONDS);
        return $ttl > 0 ? $ttl : self::HEALTH_TTL_SECONDS;
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function defaultHealth(): array
    {
        return [
            'success'         => 0,
            'error'           => 0,
            'last_error_code' => null,
            'last_error_at'   => null,
            'last_success_at' => null,
        ];
    }
}

