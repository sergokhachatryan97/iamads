<?php

namespace App\Services\Telegram\Folder;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class PremiumFolderAccountUsageLimiter
{
    public const ACTION_SUBSCRIBE = 'subscribe';

    public const ACTION_UNSUBSCRIBE = 'unsubscribe';

    public function canSubscribe(int $mtprotoAccountId): bool
    {
        return $this->canPerform($mtprotoAccountId, self::ACTION_SUBSCRIBE);
    }

    public function canUnsubscribe(int $mtprotoAccountId): bool
    {
        return $this->canPerform($mtprotoAccountId, self::ACTION_UNSUBSCRIBE);
    }

    public function recordSubscribe(int $mtprotoAccountId): void
    {
        $this->record($mtprotoAccountId, self::ACTION_SUBSCRIBE);
    }

    public function recordUnsubscribe(int $mtprotoAccountId): void
    {
        $this->record($mtprotoAccountId, self::ACTION_UNSUBSCRIBE);
    }

    private function canPerform(int $mtprotoAccountId, string $action): bool
    {
        $sub = $this->count($mtprotoAccountId, self::ACTION_SUBSCRIBE);
        $unsub = $this->count($mtprotoAccountId, self::ACTION_UNSUBSCRIBE);
        $total = $sub + $unsub;

        if ($total >= 10) {
            return false;
        }

        if ($action === self::ACTION_SUBSCRIBE && $sub >= 5) {
            return false;
        }

        if ($action === self::ACTION_UNSUBSCRIBE && $unsub >= 5) {
            return false;
        }

        return true;
    }

    private function record(int $mtprotoAccountId, string $action): void
    {
        $key = $this->key($mtprotoAccountId, $action);
        $ttl = $this->secondsUntilEndOfDay();

        // Atomic-ish for first set: INCR then EXPIRE (safe enough for counters).
        Redis::incr($key);
        Redis::expire($key, $ttl);
    }

    private function count(int $mtprotoAccountId, string $action): int
    {
        $v = Redis::get($this->key($mtprotoAccountId, $action));

        return is_numeric($v) ? (int) $v : 0;
    }

    private function key(int $mtprotoAccountId, string $action): string
    {
        $ymd = now()->format('Ymd');

        return "mtp:daily:{$ymd}:{$mtprotoAccountId}:{$action}";
    }

    private function secondsUntilEndOfDay(): int
    {
        $now = Carbon::now();
        $end = $now->copy()->endOfDay();

        return max(60, $end->diffInSeconds($now) + 1);
    }
}
