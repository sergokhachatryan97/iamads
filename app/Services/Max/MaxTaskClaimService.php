<?php

namespace App\Services\Max;

use App\Models\MaxTask;
use App\Models\Order;
use App\Support\Performer\OrderDripfeedClaimHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MaxTaskClaimService
{
    private const LEASE_TTL_SECONDS = 180;
    private const BATCH_SIZE = 50;
    private const ELIGIBLE_CACHE_TTL = 10;

    private const ACTION_RULES = [
        'subscribe'   => ['daily_cap' => 15, 'cooldown_seconds' => 1],
        'unsubscribe' => ['daily_cap' => 15, 'cooldown_seconds' => 1000],
        'bot_start'   => ['daily_cap' => 10, 'cooldown_seconds' => 500],
        'view'        => ['daily_cap' => 50, 'cooldown_seconds' => 10],
        'react'       => ['daily_cap' => 30, 'cooldown_seconds' => 30],
        'repost'      => ['daily_cap' => 10, 'cooldown_seconds' => 300],
        '_default'    => ['daily_cap' => 10, 'cooldown_seconds' => 300],
    ];

    private static ?int $maxCategoryId = null;

    // =========================================================================
    //  Public API
    // =========================================================================

    public function claim(string $accountIdentity, ?int $serviceId = null): ?array
    {
        $accountIdentity = trim($accountIdentity);
        if ($accountIdentity === '') {
            return null;
        }

        $categoryId = $this->getMaxCategoryId();
        if ($categoryId === null) {
            return null;
        }

        // Track unique accounts for stats (HyperLogLog)
        if ($serviceId !== null) {
            try {
                $hourKey = 'max:claim_attempts:' . $serviceId . ':' . now()->format('Y-m-d-H');
                Redis::pfadd($hourKey, [$accountIdentity]);
                Redis::expire($hourKey, 7200);
            } catch (\Throwable) {
            }
        }

        // Early Redis gate: check cooldown for 'subscribe' (most common action)
        // before any DB work. Non-subscribe actions are checked later.
        if ($this->isCooldownActive($accountIdentity, 'subscribe')) {
            return null;
        }
        if ($this->isDailyCapExhausted($accountIdentity, 'subscribe')) {
            return null;
        }

        return $this->claimSubscribe($accountIdentity, $categoryId, $serviceId);
    }

    // =========================================================================
    //  Unsubscribe
    // =========================================================================

    private function claimUnsubscribe(string $accountIdentity, int $categoryId, ?int $serviceId): ?array
    {
        $orderIds = DB::table('max_tasks')
            ->where('account_identity', $accountIdentity)
            ->whereIn('action', ['subscribe', 'bot_start', 'view', 'react', 'repost'])
            ->where('status', MaxTask::STATUS_DONE)
            ->pluck('order_id')
            ->unique()
            ->all();

        if (empty($orderIds)) {
            return null;
        }

        $q = Order::query()
            ->whereIn('id', $orderIds)
            ->where('execution_phase', Order::EXECUTION_PHASE_UNSUBSCRIBING)
            ->where('category_id', $categoryId);

        if ($serviceId !== null) {
            $q->where('service_id', $serviceId);
        }

        $orders = $q->limit(20)->get()->shuffle();

        foreach ($orders as $order) {
            $exists = DB::table('max_tasks')
                ->where('order_id', $order->id)
                ->where('account_identity', $accountIdentity)
                ->where('action', 'unsubscribe')
                ->exists();

            if ($exists) {
                continue;
            }

            $link = (string) $order->link;
            if (empty($link)) {
                continue;
            }

            $linkHash = md5(strtolower($link));

            $task = MaxTask::create([
                'order_id' => $order->id,
                'account_identity' => $accountIdentity,
                'action' => 'unsubscribe',
                'link' => $link,
                'link_hash' => $linkHash,
                'target_hash' => $linkHash,
                'status' => MaxTask::STATUS_LEASED,
                'leased_until' => now()->addSeconds(self::LEASE_TTL_SECONDS),
                'payload' => [
                    'order_id' => $order->id,
                    'action' => 'unsubscribe',
                    'link' => $link,
                    'account_identity' => $accountIdentity,
                ],
            ]);

            return $this->buildDto($task, $order, 'unsubscribe', $link, $linkHash);
        }

        return null;
    }

    // =========================================================================
    //  Subscribe
    // =========================================================================

    private function claimSubscribe(string $accountIdentity, int $categoryId, ?int $serviceId): ?array
    {
        $eligible = $this->getEligibleOrders($categoryId, $serviceId);

        if (empty($eligible)) {
            return null;
        }

        $now = now();

        $due = array_filter($eligible, fn (object $r) => $this->isTimingDue($r, $now));
        if (empty($due)) {
            return null;
        }

        $due = array_values($due);
        shuffle($due);

        foreach (array_chunk($due, self::BATCH_SIZE) as $batch) {
            $ids = array_map(fn ($r) => $r->id, $batch);

            $orders = Order::query()
                ->whereIn('id', $ids)
                ->where('remains', '>', 0)
                ->with('service')
                ->get()
                ->keyBy('id');

            foreach ($batch as $row) {
                $order = $orders->get($row->id);
                if (! $order) {
                    continue;
                }

                $result = $this->tryClaimForOrder($order, $accountIdentity);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    // =========================================================================
    //  Eligible orders cache
    // =========================================================================

    private function getEligibleOrders(int $categoryId, ?int $serviceId): array
    {
        $cacheKey = $serviceId !== null
            ? "max:claim:eligible:s{$serviceId}"
            : 'max:claim:eligible';

        return Cache::remember($cacheKey, self::ELIGIBLE_CACHE_TTL, function () use ($categoryId, $serviceId) {
            $q = DB::table('orders')
                ->select('id', 'remains', 'dripfeed_enabled', 'dripfeed_next_run_at', 'provider_payload')
                ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PENDING])
                ->where('remains', '>', 0)
                ->where('category_id', $categoryId);

            if ($serviceId !== null) {
                $q->where('service_id', $serviceId);
            }

            return $q->get()
                ->map(function ($row) {
                    $nextRunAt = null;
                    if (is_string($row->provider_payload)) {
                        $payload = json_decode($row->provider_payload, true);
                        $nextRunAt = $payload['execution_meta']['next_run_at'] ?? null;
                    }

                    return (object) [
                        'id' => $row->id,
                        'remains' => (int) $row->remains,
                        'dripfeed_enabled' => $row->dripfeed_enabled,
                        'dripfeed_next_run_at' => $row->dripfeed_next_run_at,
                        'next_run_at' => $nextRunAt,
                    ];
                })
                ->all();
        });
    }

    private function isTimingDue(object $row, Carbon $now): bool
    {
        if (! empty($row->dripfeed_enabled) && ! empty($row->dripfeed_next_run_at)) {
            try {
                if (Carbon::parse($row->dripfeed_next_run_at)->gt($now)) {
                    return false;
                }
            } catch (\Throwable) {
            }
        }

        if (! empty($row->next_run_at)) {
            try {
                if (Carbon::parse($row->next_run_at)->gt($now)) {
                    return false;
                }
            } catch (\Throwable) {
            }
        }

        return true;
    }

    // =========================================================================
    //  Claim transaction
    // =========================================================================

    private function tryClaimForOrder(Order $order, string $accountIdentity): ?array
    {
        $preloadedService = $order->relationLoaded('service') ? $order->service : null;

        // Pre-compute link + action OUTSIDE the transaction
        $link = trim((string) ($order->link ?? ''));
        if ($link === '') {
            return null;
        }

        $linkHash = md5(strtolower($link));
        $action = $preloadedService?->action() ?? 'subscribe';

        // Dedup check OUTSIDE transaction — no lock held during this query
        $isBlocked = DB::table('max_tasks')
            ->where('account_identity', $accountIdentity)
            ->where('link_hash', $linkHash)
            ->where('action', $action)
            ->whereIn('status', [MaxTask::STATUS_LEASED, MaxTask::STATUS_DONE])
            ->exists();

        if ($isBlocked) {
            return null;
        }

        // Rate limit check (non-subscribe actions) OUTSIDE transaction
        if ($action !== 'subscribe') {
            if ($this->isCooldownActive($accountIdentity, $action)) {
                return null;
            }
            if ($this->isDailyCapExhausted($accountIdentity, $action)) {
                return null;
            }
        }

        return DB::transaction(function () use ($order, $accountIdentity, $preloadedService, $link, $linkHash, $action): ?array {
            $order = Order::query()
                ->where('id', $order->id)
                ->where('remains', '>', 0)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return null;
            }

            if ($preloadedService) {
                $order->setRelation('service', $preloadedService);
            } else {
                $order->loadMissing('service');
            }

            if (! OrderDripfeedClaimHelper::canClaimTaskNow($order)) {
                return null;
            }

            if (! $this->canClaimBySpeedLimit($order)) {
                return null;
            }

            if (! $this->tryAcquireActionRate($accountIdentity, $action)) {
                return null;
            }

            $providerPayload = $order->provider_payload ?? [];
            $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];

            $payload = [
                'order_id' => $order->id,
                'per_call' => (int) ($executionMeta['per_call'] ?? 1),
                'action' => $action,
                'link' => $link,
                'account_identity' => $accountIdentity,
            ];

            $commentText = trim((string) ($order->comment_text ?? ''));
            if ($commentText !== '') {
                $payload['comment_text'] = $commentText;
            }

            $task = MaxTask::create([
                'order_id' => $order->id,
                'account_identity' => $accountIdentity,
                'action' => $action,
                'link' => $link,
                'link_hash' => $linkHash,
                'target_hash' => $linkHash,
                'status' => MaxTask::STATUS_LEASED,
                'leased_until' => now()->addSeconds(self::LEASE_TTL_SECONDS),
                'payload' => $payload,
            ]);

            // Post-claim updates (single merged update)
            OrderDripfeedClaimHelper::afterTaskClaimed($order);
            $this->setNextRunAt($order);
            $order->update(['status' => Order::STATUS_IN_PROGRESS]);

            return $this->buildDto($task, $order, $action, $link, $linkHash, $commentText);
        });
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    private function getMaxCategoryId(): ?int
    {
        if (self::$maxCategoryId === null) {
            self::$maxCategoryId = (int) \App\Models\Category::query()
                ->where('link_driver', 'max')
                ->value('id');
        }

        return self::$maxCategoryId ?: null;
    }

    private function canClaimBySpeedLimit(Order $order): bool
    {
        $meta = ($order->provider_payload ?? [])['execution_meta'] ?? [];
        $nextRunAt = is_array($meta) ? ($meta['next_run_at'] ?? null) : null;

        if (! $nextRunAt) {
            return true;
        }

        try {
            return Carbon::parse($nextRunAt)->lte(now());
        } catch (\Throwable) {
            return true;
        }
    }

    private function setNextRunAt(Order $order): void
    {
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];

        // Use pre-calculated interval directly (speed already applied at inspection)
        $intervalSeconds = (int) ($executionMeta['interval_seconds'] ?? 30);
        $executionMeta['next_run_at'] = now()->addSeconds(max(1, $intervalSeconds))->toDateTimeString();

        $providerPayload['execution_meta'] = $executionMeta;
        $order->update(['provider_payload' => $providerPayload]);
    }

    private function buildDto(MaxTask $task, Order $order, string $action, string $link, string $linkHash, ?string $commentText = null): array
    {
        $service = $order->relationLoaded('service') ? $order->service : null;
        $category = $service?->category;

        $serviceDescription = $service?->description_for_performer ?? '';
        if ($commentText && $commentText !== '') {
            $serviceDescription .= ($serviceDescription !== '' ? "\n" : '') . $commentText;
        }

        $dto = [
            'task_id' => $task->id,
            'link' => $link,
            'link_hash' => $linkHash,
            'action' => $action,
            'order_id' => (int) $order->id,
            'order' => [
                'id' => (string) $order->id,
                'quantity' => $order->quantity,
                'delivered' => (int) $order->delivered,
                'remains' => (int) $order->remains,
                'target_quantity' => $order->target_quantity,
            ],
            'service' => $service ? [
                'id' => $service->id,
                'name' => $service->name ?? '',
                'description' => $serviceDescription,
            ] : null,
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->name ?? '',
            ] : null,
            'comment_text' => ($commentText && $commentText !== '') ? $commentText : null,
        ];

        return $dto;
    }

    // =========================================================================
    //  Early Redis gates (non-mutating checks)
    // =========================================================================

    private function isCooldownActive(string $accountIdentity, string $action): bool
    {
        try {
            return (bool) Redis::exists("max:account:cooldown:{$action}:{$accountIdentity}");
        } catch (\Throwable) {
            return false;
        }
    }

    private function isDailyCapExhausted(string $accountIdentity, string $action): bool
    {
        $rule = $this->getActionRule($action);
        $cap = (int) $rule['daily_cap'];
        $date = Carbon::today()->format('Y-m-d');
        $key = "max:account:cap:{$action}:{$accountIdentity}:{$date}";

        try {
            return (int) Redis::get($key) >= $cap;
        } catch (\Throwable) {
            return false;
        }
    }

    // =========================================================================
    //  Action rate limiting (mutating — used only on successful claim)
    // =========================================================================

    private function getActionRule(string $action): array
    {
        return self::ACTION_RULES[$action] ?? self::ACTION_RULES['_default'];
    }

    private function tryAcquireActionRate(string $accountIdentity, string $action): bool
    {
        $rule = $this->getActionRule($action);
        $dailyCap = (int) $rule['daily_cap'];
        $cooldownSeconds = (int) $rule['cooldown_seconds'];

        if ($this->tryIncrementDailyCap($accountIdentity, $action, $dailyCap) === 0) {
            return false;
        }

        if (! $this->acquireCooldown($accountIdentity, $action, $cooldownSeconds)) {
            $this->rollbackDailyCap($accountIdentity, $action);

            return false;
        }

        return true;
    }

    private function tryIncrementDailyCap(string $accountIdentity, string $action, int $cap): int
    {
        $date = Carbon::today()->format('Y-m-d');
        $key = "max:account:cap:{$action}:{$accountIdentity}:{$date}";
        $expireAt = Carbon::today()->endOfDay()->timestamp;

        $lua = <<<'LUA'
local cap = tonumber(ARGV[1])
local expire_at = tonumber(ARGV[2])
local v = redis.call('INCR', KEYS[1])
if v == 1 then redis.call('EXPIREAT', KEYS[1], expire_at) end
if v > cap then redis.call('DECR', KEYS[1]) return 0 end
return v
LUA;

        try {
            return (int) Redis::eval($lua, 1, $key, $cap, $expireAt);
        } catch (\Throwable $e) {
            Log::error('MaxTaskClaimService::tryIncrementDailyCap: Redis error', ['key' => $key, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    private function acquireCooldown(string $accountIdentity, string $action, int $cooldownSeconds): bool
    {
        if ($cooldownSeconds <= 0) {
            return true;
        }

        try {
            $result = Redis::set("max:account:cooldown:{$action}:{$accountIdentity}", 1, 'EX', $cooldownSeconds, 'NX');

            return $result !== null && $result !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function rollbackDailyCap(string $accountIdentity, string $action): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $key = "max:account:cap:{$action}:{$accountIdentity}:{$date}";

        try {
            Redis::decr($key);
        } catch (\Throwable) {
        }
    }
}
