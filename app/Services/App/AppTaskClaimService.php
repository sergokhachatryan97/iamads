<?php

namespace App\Services\App;

use App\Models\AppTask;
use App\Models\Order;
use App\Services\ProviderActionLogService;
use App\Support\App\AppTargetNormalizer;
use App\Support\Performer\ClaimConcurrencyLimiter;
use App\Support\Performer\OrderDripfeedClaimHelper;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * App performer claim: tasks for app download + review (positive or custom with star).
 * Mirrors YouTubeTaskClaimService: cached eligible orders, fair random distribution,
 * batch loading, dripfeed-aware, speed-limit via execution_meta.next_run_at.
 */
class AppTaskClaimService
{
    private const LEASE_TTL_SECONDS = 180;

    private const ELIGIBLE_CACHE_TTL = 10;

    private static ?int $appCategoryId = null;

    public function __construct(
        private ProviderActionLogService $actionLogService
    ) {}

    // =========================================================================
    //  Public API
    // =========================================================================

    public function claim(string $accountIdentity): ?array
    {
        $accountIdentity = trim($accountIdentity);
        if ($accountIdentity === '') {
            return null;
        }

        // === Global concurrency semaphore ===
        // Reject if too many claims are already in flight across all claim
        // services (Telegram + YouTube + App). Prevents max_user_connections.
        $slot = ClaimConcurrencyLimiter::acquire();
        if ($slot === null) {
            return null;
        }

        try {
            return $this->claimInner($accountIdentity);
        } finally {
            ClaimConcurrencyLimiter::release($slot);
        }
    }

    private function claimInner(string $accountIdentity): ?array
    {
        $categoryId = $this->getAppCategoryId();
        if ($categoryId === null) {
            return null;
        }

        $now = now();

        // Cached eligible orders: id, remains, timing fields.
        // 10s TTL — shared across all performers.
        $eligible = $this->getEligibleOrders($categoryId);
        if (empty($eligible)) {
            return null;
        }

        // Pre-filter: remove orders not due yet (dripfeed / speed limit).
        $due = array_filter($eligible, fn (object $row) => $this->isTimingDue($row, $now));
        if (empty($due)) {
            return null;
        }

        // Fair random distribution — every due order has equal chance.
        $due = array_values($due);
        shuffle($due);

        // Load full models in batches of 50, try to claim
        foreach (array_chunk($due, 50) as $batch) {
            $ids = array_map(fn ($r) => $r->id, $batch);

            $orders = Order::query()
                ->whereIn('id', $ids)
                ->where('remains', '>', 0)
                ->with(['service', 'service.category'])
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

    /**
     * Cached eligible orders with only the fields needed for pre-filtering.
     * ~20KB for 1000 rows. 10s TTL in Redis.
     *
     * @return object[] {id, remains, dripfeed_enabled, dripfeed_next_run_at, next_run_at}
     */
    private function getEligibleOrders(int $categoryId): array
    {
        return Cache::remember('app:claim:eligible', self::ELIGIBLE_CACHE_TTL, function () use ($categoryId) {
            return DB::table('orders')
                ->select('id', 'remains', 'dripfeed_enabled', 'dripfeed_next_run_at', 'provider_payload')
                ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PENDING])
                ->where('mode', 'manual')
                ->where('remains', '>', 0)
                ->where('category_id', $categoryId)
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

    /**
     * Pre-filter: is this order due for a new task right now?
     */
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

    private function getAppCategoryId(): ?int
    {
        if (self::$appCategoryId === null) {
            self::$appCategoryId = (int) \App\Models\Category::query()
                ->where('link_driver', 'app')
                ->value('id');
        }

        return self::$appCategoryId ?: null;
    }

    // =========================================================================
    //  Claim transaction
    // =========================================================================

    private function tryClaimForOrder(Order $order, string $accountIdentity): ?array
    {
        $preloadedService = $order->relationLoaded('service') ? $order->service : null;

        return DB::transaction(function () use ($order, $accountIdentity, $preloadedService): ?array {
            $order = Order::query()
                ->where('id', $order->id)
                ->where('remains', '>', 0)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                return null;
            }

            if ($preloadedService !== null) {
                $order->setRelation('service', $preloadedService);
            } else {
                $order->loadMissing(['service', 'service.category']);
            }

            // Dripfeed gate (authoritative, under lock)
            if (! OrderDripfeedClaimHelper::canClaimTaskNow($order)) {
                return null;
            }

            // Speed limit gate (authoritative, under lock)
            if (! $this->canClaimBySpeedLimit($order)) {
                return null;
            }

            $plan = AppExecutionPlanResolver::resolve($order);
            $action = $plan['action'];
            $steps = $plan['steps'];

            $link = trim((string) ($order->link ?? ''));
            if ($link === '') {
                return null;
            }

            $linkHash = AppTargetNormalizer::linkHash($link);
            $targetHash = AppTargetNormalizer::targetHash($order);

            // In-flight count
            $inFlight = AppTask::query()
                ->where('order_id', $order->id)
                ->where('status', AppTask::STATUS_LEASED)
                ->count();

            $target = $order->target_quantity;
            if ((int) $order->delivered + $inFlight >= $target) {
                return null;
            }

            // Uniqueness checks
            $actionNames = AppExecutionPlanResolver::stepsToActionNames($steps);
            if ($this->hasStepConflict($accountIdentity, $targetHash, $actionNames)) {
                return null;
            }

            if (AppTask::query()
                ->where('account_identity', $accountIdentity)
                ->where('order_id', $order->id)
                ->where('link_hash', $linkHash)
                ->where('action', $action)
                ->exists()) {
                return null;
            }

            // Build task
            $leasedUntil = now()->addSeconds(self::LEASE_TTL_SECONDS);
            $payload = [
                'order_id' => $order->id,
                'per_call' => 1,
                'action' => $action,
                'steps' => $steps,
                'target_hash' => $targetHash,
            ];

            // comment_text: pick one per account_identity — index = delivered + inFlight
            $commentTextForTask = null;
            if (in_array('custom_review', $steps, true) && ! empty(trim((string) ($order->comment_text ?? '')))) {
                $comments = array_values(array_filter(array_map('trim', explode("\n", (string) $order->comment_text))));
                $index = (int) $order->delivered + $inFlight;
                if (! empty($comments)) {
                    $commentTextForTask = $comments[$index % count($comments)];
                } else {
                    $commentTextForTask = trim((string) $order->comment_text);
                }
                if ($commentTextForTask !== '') {
                    $payload['comment_text'] = $commentTextForTask;
                }
            }

            // star_rating
            $starRating = $order->star_rating ?? (($order->provider_payload ?? [])['star_rating'] ?? null);
            if ($starRating !== null && $starRating >= 1 && $starRating <= 5) {
                if (in_array('custom_review', $steps, true) || in_array('positive_review', $steps, true)) {
                    $payload['star_rating'] = (int) $starRating;
                }
            }

            try {
                $task = AppTask::create([
                    'order_id' => $order->id,
                    'account_identity' => $accountIdentity,
                    'action' => $action,
                    'link' => $link,
                    'link_hash' => $linkHash,
                    'target_hash' => $targetHash,
                    'status' => AppTask::STATUS_LEASED,
                    'leased_until' => $leasedUntil,
                    'payload' => $payload,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                Log::debug('App task claim duplicate', [
                    'account_identity' => $accountIdentity,
                    'order_id' => $order->id,
                    'action' => $action,
                ]);
                return null;
            }

            // Post-claim: update dripfeed counters
            OrderDripfeedClaimHelper::afterTaskClaimed($order);

            // Post-claim: set speed-limit next_run_at
            $this->setNextRunAt($order);

            $order->update(['status' => Order::STATUS_IN_PROGRESS]);

            // Build response
            $service = $order->service;
            $category = $service?->category;

            $serviceDescription = $service?->description_for_performer ?? '';
            if ($commentTextForTask !== null && trim((string) $commentTextForTask) !== '') {
                $serviceDescription .= ($serviceDescription !== '' ? "\n" : '') . sprintf('Review: %s', $commentTextForTask);
            }
            if (isset($payload['star_rating'])) {
                $serviceDescription .= ($serviceDescription !== '' ? "\n" : '') . sprintf('Star rating: %d', $payload['star_rating']);
            }

            $result = [
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
                    'dripfeed_enabled' => (bool) ($order->dripfeed_enabled ?? false),
                ],
                'service' => [
                    'id' => $service?->id,
                    'name' => $service?->name ?? '',
                    'description' => $serviceDescription,
                    'service_description' => $serviceDescription,
                ],
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name ?? '',
                ] : null,
            ];

            if (count($steps) > 1) {
                $result['mode'] = 'combo';
                $result['steps'] = $steps;
            }
            if (! empty($payload['comment_text'] ?? '')) {
                $result['comment_text'] = $payload['comment_text'];
            }
            if (isset($payload['star_rating'])) {
                $result['star_rating'] = $payload['star_rating'];
            }

            return $result;
        });
    }

    // =========================================================================
    //  Speed limit
    // =========================================================================

    private function canClaimBySpeedLimit(Order $order): bool
    {
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];
        $nextRunAt = $executionMeta['next_run_at'] ?? null;

        if ($nextRunAt === null) {
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

        $intervalSeconds = (int) ($executionMeta['interval_seconds'] ?? 30);
        $executionMeta['next_run_at'] = now()->addSeconds(max(1, $intervalSeconds))->toDateTimeString();

        $providerPayload['execution_meta'] = $executionMeta;
        $order->update(['provider_payload' => $providerPayload]);
    }

    // =========================================================================
    //  Conflict checks
    // =========================================================================

    private function hasStepConflict(string $accountIdentity, string $targetHash, array $actionNames): bool
    {
        $activeStatuses = [AppTask::STATUS_LEASED, AppTask::STATUS_PENDING];

        $activeTasks = AppTask::query()
            ->where('account_identity', $accountIdentity)
            ->where('target_hash', $targetHash)
            ->whereIn('status', $activeStatuses)
            ->get(['id', 'action', 'payload']);

        foreach ($activeTasks as $task) {
            $payload = $task->payload ?? [];
            $steps = is_array($payload) && isset($payload['steps']) ? $payload['steps'] : [$task->action];
            $existingNames = AppExecutionPlanResolver::stepsToActionNames($steps);
            if (array_intersect($actionNames, $existingNames) !== []) {
                return true;
            }
        }

        foreach ($actionNames as $actionName) {
            if ($this->actionLogService->hasPerformed(
                ProviderActionLogService::PROVIDER_APP,
                $accountIdentity,
                $targetHash,
                $actionName
            )) {
                return true;
            }
        }

        if (count($actionNames) > 1) {
            $compositeAction = AppExecutionPlanResolver::compositeActionForLog($actionNames);
            if ($this->actionLogService->hasPerformed(
                ProviderActionLogService::PROVIDER_APP,
                $accountIdentity,
                $targetHash,
                $compositeAction
            )) {
                return true;
            }
        }

        return false;
    }
}
