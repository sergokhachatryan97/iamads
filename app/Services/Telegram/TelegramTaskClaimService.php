<?php

namespace App\Services\Telegram;

use App\Models\Order;
use App\Models\TelegramAccountLinkState;
use App\Models\TelegramFolderMembership;
use App\Models\TelegramOrderMembership;
use App\Models\TelegramTask;
use App\Support\TelegramPremiumTemplateScope;
use App\Support\TelegramSystemManagedTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TelegramTaskClaimService
{
    private const LEASE_TTL_SECONDS = 90;

    private const PHONE_ACTIVE_SUBSCRIBED_CAP = 500;

    private const PHONE_ACTIVE_SUBSCRIBED_CAP_PREMIUM = 1000;

    private const BATCH_SIZE = 50;

    private const ELIGIBLE_CACHE_TTL = 10;

    /** Per-request cache for getPhoneActiveSubscribedCount — avoids repeated COUNT in loop. */
    private array $activeSubscribedCache = [];

    private const ACTION_RULES = [
        TelegramPremiumTemplateScope::SCOPE_DEFAULT => [
            'subscribe' => ['daily_cap' => 15, 'cooldown_seconds' => 1300],
            'unsubscribe' => ['daily_cap' => 15, 'cooldown_seconds' => 1300],
            'bot_start' => ['daily_cap' => 10, 'cooldown_seconds' => 600],
            'view' => ['daily_cap' => 50, 'cooldown_seconds' => 10],
            'react' => ['daily_cap' => 30, 'cooldown_seconds' => 30],
            'vote' => ['daily_cap' => 20, 'cooldown_seconds' => 60],
            'repost' => ['daily_cap' => 10, 'cooldown_seconds' => 300],
            'comment' => ['daily_cap' => 10, 'cooldown_seconds' => 300],
            'story_repost' => ['daily_cap' => 15, 'cooldown_seconds' => 120],
            'story_like' => ['daily_cap' => 30, 'cooldown_seconds' => 30],
            '_default' => ['daily_cap' => 10, 'cooldown_seconds' => 300],
        ],
        TelegramPremiumTemplateScope::SCOPE_PREMIUM => [
            'subscribe' => ['daily_cap' => 18, 'cooldown_seconds' => 1200],
            'unsubscribe' => ['daily_cap' => 18, 'cooldown_seconds' => 1200],
            'bot_start' => ['daily_cap' => 15, 'cooldown_seconds' => 300],
            'view' => ['daily_cap' => 80, 'cooldown_seconds' => 5],
            'react' => ['daily_cap' => 50, 'cooldown_seconds' => 20],
            'vote' => ['daily_cap' => 30, 'cooldown_seconds' => 30],
            'repost' => ['daily_cap' => 15, 'cooldown_seconds' => 180],
            'comment' => ['daily_cap' => 15, 'cooldown_seconds' => 180],
            'story_repost' => ['daily_cap' => 20, 'cooldown_seconds' => 60],
            'story_like' => ['daily_cap' => 50, 'cooldown_seconds' => 20],
            '_default' => ['daily_cap' => 15, 'cooldown_seconds' => 180],
        ],
    ];

    public function __construct(
        private TelegramTaskService $taskService
    ) {}

    // =========================================================================
    //  Cached lookups
    // =========================================================================

    private function getTelegramCategoryId(): ?int
    {
        return Cache::remember('tg:category_id', 3600, fn () => \App\Models\Category::where('link_driver', 'telegram')->value('id')
        );
    }

    private function getSystemManagedKeys(): array
    {
        return Cache::remember('tg:system_managed_keys', 3600, fn () => TelegramSystemManagedTemplate::templateKeys()
        );
    }

    // =========================================================================
    //  Public API
    // =========================================================================

    public function claimForPhone(string $phone, int $limit = 1, string $scope = TelegramPremiumTemplateScope::SCOPE_DEFAULT, ?int $serviceId = null): array
    {
        $phone = TelegramAccountLinkState::normalizePhone($phone);

        $tasks = [];
        for ($i = 0; $i < $limit; $i++) {
            $taskDto = $this->claimSingle($phone, $scope, $serviceId);
            if ($taskDto === null) {
                break;
            }
            $tasks[] = $taskDto;
        }

        return $tasks;
    }

    private function claimSingle(string $phone, string $scope, ?int $serviceId): ?array
    {
        // Priority 1: unsubscribe (regular + folder)
        $result = $this->claimUnsubscribe($phone, $scope, $serviceId);
        if ($result !== null) {
            return $result;
        }

        if ($scope === TelegramPremiumTemplateScope::SCOPE_PREMIUM) {
            $result = $this->claimPremiumFolderUnsubscribe($phone, $serviceId);
            if ($result !== null) {
                return $result;
            }
        }

        // Priority 2: subscribe — random between folder and regular for premium
        if ($scope === TelegramPremiumTemplateScope::SCOPE_PREMIUM) {
            if (random_int(0, 1) === 0) {
                return $this->claimPremiumFolderSubscribe($phone, $serviceId)
                    ?? $this->claimSubscribe($phone, $scope, $serviceId);
            }

            return $this->claimSubscribe($phone, $scope, $serviceId)
                ?? $this->claimPremiumFolderSubscribe($phone, $serviceId);
        }

        return $this->claimSubscribe($phone, $scope, $serviceId);
    }

    // =========================================================================
    //  Premium folder unsubscribe
    // =========================================================================

    private function claimPremiumFolderUnsubscribe(string $phone, ?int $serviceId = null): ?array
    {
        $systemManagedKeys = $this->getSystemManagedKeys();
        if ($systemManagedKeys === []) {
            return null;
        }

        $categoryId = $this->getTelegramCategoryId();
        if (! $categoryId) {
            return null;
        }

        $orderIds = DB::table('telegram_tasks')
            ->where('action', 'folder_add')
            ->where('status', TelegramTask::STATUS_DONE)
            ->where('telegram_account_id', $phone)
            ->pluck('order_id')
            ->unique()
            ->all();

        if (empty($orderIds)) {
            return null;
        }

        $q = Order::query()
            ->whereIn('id', $orderIds)
            ->where('execution_phase', Order::EXECUTION_PHASE_UNSUBSCRIBING)
            ->where('category_id', $categoryId)
            ->whereHas('telegramFolderMembership', fn ($q) => $q->where('status', TelegramFolderMembership::STATUS_REMOVED));

        if ($serviceId !== null) {
            $q->where('service_id', $serviceId);
        }

        $orders = $q
            ->limit(20)
            ->get()
            ->shuffle();

        foreach ($orders as $order) {
            // DB-level dedupe: check if unsubscribe task already exists for this phone+order
            $exists = DB::table('telegram_tasks')
                ->where('order_id', $order->id)
                ->where('action', 'unsubscribe')
                ->where('telegram_account_id', $phone)
                ->exists();

            if ($exists) {
                continue;
            }

            // Redis dedupe for concurrent requests (atomic SET NX)
            $dedupeKey = "tg:folder_unsub:{$order->id}:{$phone}";
            if (! Cache::add($dedupeKey, 1, 3600)) {
                continue;
            }

            $link = (string) $order->link;
            if (empty($link)) {
                continue;
            }

            $linkHash = TelegramAccountLinkState::linkHash($link);

            $task = TelegramTask::create([
                'order_id' => $order->id,
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'action' => 'unsubscribe',
                'link_hash' => $linkHash,
                'telegram_account_id' => $phone,
                'provider_account_id' => null,
                'status' => TelegramTask::STATUS_LEASED,
                'leased_until' => now()->addSeconds(self::LEASE_TTL_SECONDS),
                'attempt' => 0,
                'payload' => [
                    'link' => $link,
                    'link_hash' => $linkHash,
                    'action' => 'unsubscribe',
                    'account_phone' => $phone,
                    'subject' => ['type' => 'order', 'id' => $order->id],
                    'premium_folder' => true,
                ],
            ]);

            return [
                'task_id' => $task->id,
                'order_id' => (int) $order->id,
                'action' => 'unsubscribe',
                'link' => $link,
                'link_hash' => $linkHash,
            ];
        }

        return null;
    }

    // =========================================================================
    //  Premium folder subscribe
    // =========================================================================

    private function claimPremiumFolderSubscribe(string $phone, ?int $serviceId = null): ?array
    {
        $systemManagedKeys = $this->getSystemManagedKeys();
        if ($systemManagedKeys === []) {
            return null;
        }

        $categoryId = $this->getTelegramCategoryId();
        if (! $categoryId) {
            return null;
        }

        $q = Order::query()
            ->with('service')
            ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS])
            ->where('execution_phase', Order::EXECUTION_PHASE_RUNNING)
            ->where('remains', '>', 0)
            ->where('category_id', $categoryId)
            ->whereHas('service', fn ($sq) => $sq->whereIn('template_key', $systemManagedKeys));

        if ($serviceId !== null) {
            $q->where('service_id', $serviceId);
        }

        $order = $q->get()->shuffle()->first();

        if (! $order) {
            return null;
        }

        // DB-level dedupe: already has a folder_add task for this phone+order?
        $exists = DB::table('telegram_tasks')
            ->where('order_id', $order->id)
            ->where('action', 'folder_add')
            ->where('telegram_account_id', $phone)
            ->exists();

        if ($exists) {
            return null;
        }

        // Redis dedupe for concurrent requests
        $dedupeKey = "tg:folder_sub:{$order->id}:{$phone}";
        if (! Cache::add($dedupeKey, 1, 86400)) {
            return null;
        }

        $link = (string) $order->link;
        if (empty($link)) {
            return null;
        }

        $providerPayload = $order->provider_payload ?? [];
        $folderCfg = is_array($providerPayload['telegram_premium_folder'] ?? null) ? $providerPayload['telegram_premium_folder'] : [];
        $folderShareLink = $folderCfg['folder_share_link'] ?? null;

        $linkHash = TelegramAccountLinkState::linkHash($link);
        $commentText = trim((string) ($order->comment_text ?? ''));

        $payload = [
            'link' => $link,
            'link_hash' => $linkHash,
            'action' => 'folder_add',
            'account_phone' => $phone,
            'subject' => ['type' => 'order', 'id' => $order->id],
            'premium_folder' => true,
            'folder_share_link' => $folderShareLink,
        ];
        if ($commentText !== '') {
            $payload['comment_text'] = $commentText;
        }

        $task = TelegramTask::create([
            'order_id' => $order->id,
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'action' => 'folder_add',
            'link_hash' => $linkHash,
            'telegram_account_id' => $phone,
            'provider_account_id' => null,
            'status' => TelegramTask::STATUS_LEASED,
            'leased_until' => now()->addSeconds(self::LEASE_TTL_SECONDS),
            'attempt' => 0,
            'payload' => $payload,
        ]);

        if ($order->status === Order::STATUS_AWAITING) {
            $order->update(['status' => Order::STATUS_IN_PROGRESS]);
        }

        $dto = [
            'task_id' => $task->id,
            'order_id' => (int) $order->id,
            'action' => 'folder_add',
            'link' => $folderShareLink ?? $link,
            'link_hash' => $linkHash,
        ];
        if ($commentText !== '') {
            $dto['comment_text'] = $commentText;
        }

        return $dto;
    }

    // =========================================================================
    //  Regular unsubscribe
    // =========================================================================

    private function claimUnsubscribe(string $phone, string $scope, ?int $serviceId = null): ?array
    {
        $categoryId = $this->getTelegramCategoryId();
        if (! $categoryId) {
            return null;
        }

        return DB::transaction(function () use ($phone, $scope, $categoryId, $serviceId): ?array {
            $membership = TelegramOrderMembership::query()
                ->where('account_phone', $phone)
                ->where('state', TelegramOrderMembership::STATE_SUBSCRIBED)
                ->whereNull('unsubscribed_at')
                ->whereHas('order', function ($q) use ($scope, $categoryId, $serviceId) {
                    $q->where('execution_phase', Order::EXECUTION_PHASE_UNSUBSCRIBING)
                        ->where('category_id', $categoryId)
                        ->whereHas('service', fn ($sq) => TelegramPremiumTemplateScope::applyServiceTemplateScope($sq, $scope));
                    if ($serviceId !== null) {
                        $q->where('service_id', $serviceId);
                    }
                })
                ->lockForUpdate()
                ->first();

            if (! $membership) {
                return null;
            }

            $order = $membership->order;
            if (! $order) {
                return null;
            }

            $order->loadMissing('service');

            $dueAt = $order->completed_at?->copy()
                ->addDays(max(1, (int) ($order->service->duration_days ?? 1)));

            if ($dueAt && now()->lt($dueAt)) {
                return null;
            }

            $link = $order->link;
            $linkHash = TelegramAccountLinkState::linkHash($link);
            $action = 'unsubscribe';

            if ($this->tryIncrementPhoneDailyCap($phone, $scope, $action) === 0) {
                return null;
            }

            $cooldownSeconds = $this->getActionCooldownSeconds($scope, $action);
            if (! $this->acquirePhoneCooldown($phone, $action, $cooldownSeconds)) {
                $this->rollbackPhoneDailyCap($phone, $scope, $action);

                return null;
            }

            $payload = $this->buildClaimPayload($order, $action, $link, $linkHash, $phone);

            $task = TelegramTask::create([
                'order_id' => $order->id,
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'action' => $action,
                'link_hash' => $linkHash,
                'telegram_account_id' => null,
                'provider_account_id' => null,
                'status' => TelegramTask::STATUS_LEASED,
                'leased_until' => now()->addSeconds(self::LEASE_TTL_SECONDS),
                'attempt' => 0,
                'payload' => array_merge($payload, ['account_phone' => $phone]),
            ]);

            $membership->update([
                'state' => TelegramOrderMembership::STATE_IN_PROGRESS,
                'unsubscribed_task_id' => $task->id,
            ]);

            $dto = [
                'task_id' => $task->id,
                'order_id' => (int) $order->id,
                'action' => $action,
                'link' => $link,
                'link_hash' => $linkHash,
            ];

            return $dto;
        });
    }

    // =========================================================================
    //  Regular subscribe
    // =========================================================================

    private function claimSubscribe(string $phone, string $scope, ?int $serviceId = null): ?array
    {
        $eligible = $this->getEligibleSubscribeOrders($scope, $serviceId);

        if (empty($eligible)) {
            return null;
        }

        $now = now();

        // Pre-filter: timing (dripfeed + speed limit)
        $due = array_filter($eligible, fn (object $r) => $this->isTimingDue($r, $now));

        if (empty($due)) {
            return null;
        }

        // Fair random distribution — every due order has equal chance.
        // Speed limit (next_run_at) controls per-order pacing.
        $due = array_values($due);
        shuffle($due);

        // Batch load
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

                $result = $this->tryClaimSubscribeForOrder($order, $phone, $scope);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function getEligibleSubscribeOrders(string $scope, ?int $serviceId = null): array
    {
        $categoryId = $this->getTelegramCategoryId();
        if (! $categoryId) {
            return [];
        }

        $cacheKey = $serviceId !== null
            ? "tg:claim:eligible:{$scope}:s{$serviceId}"
            : "tg:claim:eligible:{$scope}";

        return Cache::remember($cacheKey, self::ELIGIBLE_CACHE_TTL, function () use ($categoryId, $scope, $serviceId) {
            $systemManagedKeys = $this->getSystemManagedKeys();

            if ($serviceId !== null) {
                // Specific service requested — use it directly
                $serviceIds = [$serviceId];
            } else {
                $serviceQuery = \App\Models\Service::query()->where('category_id', $categoryId);
                TelegramPremiumTemplateScope::applyServiceTemplateScope($serviceQuery, $scope);
                if ($systemManagedKeys !== []) {
                    $serviceQuery->whereNotIn('template_key', $systemManagedKeys);
                }
                $serviceIds = $serviceQuery->pluck('id')->all();
            }

            if (empty($serviceIds)) {
                return [];
            }

            return DB::table('orders')
                ->select('id', 'remains', 'dripfeed_enabled', 'dripfeed_next_run_at', 'provider_payload')
                ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING])
                ->where('remains', '>', 0)
                ->where('category_id', $categoryId)
                ->whereIn('service_id', $serviceIds)
                ->where(function ($q) {
                    $q->whereNull('execution_phase')->orWhere('execution_phase', Order::EXECUTION_PHASE_RUNNING);
                })
                ->get()
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
    //  tryClaimSubscribeForOrder
    // =========================================================================

    private function tryClaimSubscribeForOrder(Order $preloaded, string $phone, string $scope): ?array
    {
        $preloadedService = $preloaded->relationLoaded('service') ? $preloaded->service : null;

        return DB::transaction(function () use ($preloaded, $phone, $scope, $preloadedService): ?array {
            $order = Order::query()
                ->where('id', $preloaded->id)
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

            // In-flight check
            $inFlight = TelegramOrderMembership::query()
                ->where('order_id', $order->id)
                ->where('state', TelegramOrderMembership::STATE_IN_PROGRESS)
                ->count();

            if ((int) $order->delivered + $inFlight >= $order->target_quantity) {
                return null;
            }

            // Duplicate membership check
            $link = $order->link;
            if (empty($link)) {
                return null;
            }

            $linkHash = TelegramAccountLinkState::linkHash($link);

            $hasMembership = TelegramOrderMembership::query()
                ->where('order_id', $order->id)
                ->where('account_phone', $phone)
                ->where('link_hash', $linkHash)
                ->whereIn('state', [TelegramOrderMembership::STATE_SUBSCRIBED, TelegramOrderMembership::STATE_IN_PROGRESS])
                ->exists();

            if ($hasMembership) {
                return null;
            }

            $action = $this->resolveOrderAction($order);

            // Global state lock
            $global = TelegramAccountLinkState::query()
                ->where('account_phone', $phone)
                ->where('link_hash', $linkHash)
                ->lockForUpdate()
                ->first();

            if ($global && in_array($global->state, [
                TelegramAccountLinkState::STATE_IN_PROGRESS,
                TelegramAccountLinkState::STATE_SUBSCRIBED,
            ], true)) {
                return null;
            }

            // Dripfeed gate (authoritative)
            if ((bool) ($order->dripfeed_enabled ?? false)) {
                $runsTotal = (int) ($order->dripfeed_runs_total ?? 0);
                $runIndex = (int) ($order->dripfeed_run_index ?? 0);
                $perRunQty = (int) ($order->dripfeed_quantity ?? 0);
                $deliveredInRun = (int) ($order->dripfeed_delivered_in_run ?? 0);

                if ($runsTotal > 0 && $runIndex >= $runsTotal) {
                    return null;
                }
                if (! empty($order->dripfeed_next_run_at)) {
                    try {
                        if (Carbon::parse($order->dripfeed_next_run_at)->isFuture()) {
                            return null;
                        }
                    } catch (\Throwable) {
                    }
                }
                if ($perRunQty > 0 && $deliveredInRun >= $perRunQty) {
                    $order->update([
                        'dripfeed_run_index' => $runIndex + 1,
                        'dripfeed_delivered_in_run' => 0,
                        'dripfeed_next_run_at' => now()->addMinutes(max(1, (int) ($order->dripfeed_interval_minutes ?? 60)))->toDateTimeString(),
                    ]);

                    return null;
                }
            }

            // Speed limit gate (authoritative)
            $providerPayload = $order->provider_payload ?? [];
            $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];
            $nextRunAt = $executionMeta['next_run_at'] ?? null;
            if ($nextRunAt) {
                try {
                    if (Carbon::parse($nextRunAt)->isFuture()) {
                        return null;
                    }
                } catch (\Throwable) {
                }
            }

            // Active membership cap
            $normalizedAction = $this->normalizeAction($action);
            if (in_array($normalizedAction, ['subscribe', 'unsubscribe'], true)) {
                if ($this->getPhoneActiveSubscribedCount($phone) >= $this->phoneActiveSubscribedCap($scope)) {
                    return null;
                }
            }

            // Cap + cooldown
            $capAction = $this->normalizeAction($action);
            if ($this->tryIncrementPhoneDailyCap($phone, $scope, $capAction) === 0) {
                return null;
            }

            $cooldownSeconds = $this->getActionCooldownSeconds($scope, $capAction);
            if (! $this->acquirePhoneCooldown($phone, $capAction, $cooldownSeconds)) {
                $this->rollbackPhoneDailyCap($phone, $scope, $capAction);

                return null;
            }

            // === Mutations ===

            // Global state
            if ($global) {
                $global->update(['state' => TelegramAccountLinkState::STATE_IN_PROGRESS, 'last_error' => null]);
            } else {
                try {
                    $global = TelegramAccountLinkState::create([
                        'account_phone' => $phone,
                        'link_hash' => $linkHash,
                        'state' => TelegramAccountLinkState::STATE_IN_PROGRESS,
                    ]);
                } catch (\Throwable $e) {
                    if (! $this->isDuplicateKeyException($e)) {
                        throw $e;
                    }
                    $global = TelegramAccountLinkState::query()
                        ->where('account_phone', $phone)
                        ->where('link_hash', $linkHash)
                        ->lockForUpdate()
                        ->first();

                    if (! $global || in_array($global->state, [
                        TelegramAccountLinkState::STATE_IN_PROGRESS,
                        TelegramAccountLinkState::STATE_SUBSCRIBED,
                    ], true)) {
                        $this->rollbackCapAndCooldown($phone, $scope, $capAction, $cooldownSeconds);

                        return null;
                    }
                    $global->update(['state' => TelegramAccountLinkState::STATE_IN_PROGRESS, 'last_error' => null]);
                }
            }

            // Membership
            try {
                $membership = TelegramOrderMembership::create([
                    'order_id' => $order->id,
                    'account_phone' => $phone,
                    'link_hash' => $linkHash,
                    'link' => $link,
                    'state' => TelegramOrderMembership::STATE_IN_PROGRESS,
                ]);
            } catch (\Throwable $e) {
                if ($this->isDuplicateKeyException($e)) {
                    $this->rollbackCapAndCooldown($phone, $scope, $capAction, $cooldownSeconds);

                    return null;
                }
                throw $e;
            }

            // Task
            $payload = $this->buildClaimPayload($order, $action, $link, $linkHash, $phone);
            $task = TelegramTask::create([
                'order_id' => $order->id,
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'action' => $action,
                'link_hash' => $linkHash,
                'telegram_account_id' => null,
                'provider_account_id' => null,
                'status' => TelegramTask::STATUS_LEASED,
                'leased_until' => now()->addSeconds(self::LEASE_TTL_SECONDS),
                'attempt' => 0,
                'payload' => array_merge($payload, ['account_phone' => $phone]),
            ]);

            // Post-claim: single merged update for dripfeed + speed limit + status
            $orderUpdates = ['status' => Order::STATUS_IN_PROGRESS];

            if ($order->execution_phase === null) {
                $orderUpdates['execution_phase'] = Order::EXECUTION_PHASE_RUNNING;
            }

            // Dripfeed tracking
            if ((bool) ($order->dripfeed_enabled ?? false)) {
                $perRunQty = (int) ($order->dripfeed_quantity ?? 0);
                $deliveredInRun = (int) ($order->dripfeed_delivered_in_run ?? 0) + 1;
                $runIndex = (int) ($order->dripfeed_run_index ?? 0);
                $runsTotal = (int) ($order->dripfeed_runs_total ?? 0);

                $orderUpdates['dripfeed_delivered_in_run'] = $deliveredInRun;
                if ($perRunQty > 0 && $deliveredInRun >= $perRunQty) {
                    $intervalMinutes = max(1, (int) ($order->dripfeed_interval_minutes ?? 60));
                    $orderUpdates['dripfeed_run_index'] = $runIndex + 1;
                    $orderUpdates['dripfeed_delivered_in_run'] = 0;
                    $orderUpdates['dripfeed_next_run_at'] = now()->addMinutes($intervalMinutes)->toDateTimeString();
                    if ($runsTotal > 0 && ($runIndex + 1) >= $runsTotal) {
                        $orderUpdates['dripfeed_enabled'] = false;
                    }
                }
            }

            // Speed limit: set next_run_at
            $baseInterval = (int) ($executionMeta['interval_seconds'] ?? 30);
            $speed = (float) ($order->speed_multiplier ?? ($executionMeta['speed_multiplier'] ?? 1));
            $speed = $speed > 0 ? $speed : 1.0;
            $executionMeta['next_run_at'] = now()->addSeconds((int) max(1, round($baseInterval / $speed)))->toDateTimeString();
            $providerPayload['execution_meta'] = $executionMeta;
            $orderUpdates['provider_payload'] = $providerPayload;

            $order->update($orderUpdates);

            $global->update(['last_task_id' => $task->id]);
            $membership->update(['subscribed_task_id' => $task->id]);

            $dto = [
                'task_id' => $task->id,
                'order_id' => (int) $order->id,
                'action' => $action,
                'link' => $link,
                'link_hash' => $linkHash,
            ];

            return $dto;
        });
    }

    // =========================================================================
    //  Action helpers
    // =========================================================================

    private function normalizeAction(string $action): string
    {
        return match ($action) {
            'invite_subscribers' => 'subscribe',
            default => $action,
        };
    }

    private function resolveOrderAction(Order $order): string
    {
        $templateAction = $order->service?->action();
        if ($templateAction !== null && $templateAction !== '') {
            return $templateAction;
        }

        $meta = ($order->provider_payload ?? [])['execution_meta'] ?? [];

        return (string) (is_array($meta) ? ($meta['action'] ?? '') : '') ?: 'subscribe';
    }

    private function getActionRule(string $scope, string $action): array
    {
        return (self::ACTION_RULES[$scope] ?? self::ACTION_RULES[TelegramPremiumTemplateScope::SCOPE_DEFAULT])[$action]
            ?? (self::ACTION_RULES[$scope] ?? self::ACTION_RULES[TelegramPremiumTemplateScope::SCOPE_DEFAULT])['_default'];
    }

    private function getActionDailyCap(string $scope, string $action): int
    {
        return (int) $this->getActionRule($scope, $action)['daily_cap'];
    }

    private function getActionCooldownSeconds(string $scope, string $action): int
    {
        return (int) $this->getActionRule($scope, $action)['cooldown_seconds'];
    }

    // =========================================================================
    //  Redis rate limiting
    // =========================================================================

    private function tryIncrementPhoneDailyCap(string $phone, string $scope, string $action): int
    {
        $date = Carbon::today()->format('Y-m-d');
        $key = "tg:phone:cap:{$scope}:{$action}:{$phone}:{$date}";
        $cap = $this->getActionDailyCap($scope, $action);
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
            Log::error('tryIncrementPhoneDailyCap: Redis error', ['key' => $key, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    private function acquirePhoneCooldown(string $phone, string $action, int $cooldownSeconds): bool
    {
        if ($cooldownSeconds <= 0) {
            return true;
        }

        try {
            $result = Redis::set("tg:phone:cooldown:{$action}:{$phone}", 1, 'EX', $cooldownSeconds, 'NX');

            return $result !== null && $result !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function rollbackPhoneDailyCap(string $phone, string $scope, string $action): void
    {
        try {
            Redis::decr("tg:phone:cap:{$scope}:{$action}:{$phone}:".Carbon::today()->format('Y-m-d'));
        } catch (\Throwable) {
        }
    }

    private function rollbackPhoneCooldown(string $phone, string $action): void
    {
        try {
            Redis::del("tg:phone:cooldown:{$action}:{$phone}");
        } catch (\Throwable) {
        }
    }

    private function rollbackCapAndCooldown(string $phone, string $scope, string $action, int $cooldownSeconds): void
    {
        $this->rollbackPhoneDailyCap($phone, $scope, $action);
        if ($cooldownSeconds > 0) {
            $this->rollbackPhoneCooldown($phone, $action);
        }
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    private function buildClaimPayload(Order $order, string $action, ?string $link, string $linkHash, string $phone): array
    {
        $providerPayload = $order->provider_payload ?? [];
        $telegramData = $providerPayload['telegram'] ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];
        $parsed = is_array($telegramData['parsed'] ?? null) ? $telegramData['parsed'] : [];

        return [
            'link' => $link,
            'link_hash' => $linkHash,
            'action' => $action,
            'per_call' => (int) ($executionMeta['per_call'] ?? 1),
            'meta' => $executionMeta,
            'parsed' => $parsed,
            'subject' => ['type' => 'order', 'id' => $order->id],
            'account_phone' => $phone,
        ];
    }

    private function getPhoneActiveSubscribedCount(string $phone): int
    {
        if (! isset($this->activeSubscribedCache[$phone])) {
            $this->activeSubscribedCache[$phone] = TelegramAccountLinkState::query()
                ->where('account_phone', $phone)
                ->where('state', TelegramAccountLinkState::STATE_SUBSCRIBED)
                ->count();
        }

        return $this->activeSubscribedCache[$phone];
    }

    private function phoneActiveSubscribedCap(string $scope): int
    {
        return $scope === TelegramPremiumTemplateScope::SCOPE_PREMIUM
            ? self::PHONE_ACTIVE_SUBSCRIBED_CAP_PREMIUM
            : self::PHONE_ACTIVE_SUBSCRIBED_CAP;
    }

    private function isDuplicateKeyException(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'Duplicate entry') || str_contains($msg, 'unique') || ($e->getCode() ?? 0) === 23000;
    }
}
