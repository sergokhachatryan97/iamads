<?php

namespace App\Services\YouTube;

use App\Models\Order;
use App\Models\YouTubeTask;
use App\Services\ProviderActionLogService;
use App\Support\Performer\OrderDripfeedClaimHelper;
use App\Support\YouTube\YouTubeTargetNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YouTubeTaskClaimService
{
    private const LEASE_TTL_SECONDS = 180;

    /** How many random offset attempts before fallback. */
    private const MAX_RANDOM_ATTEMPTS = 5;

    /** Rows to grab per random offset attempt. */
    private const BATCH_SIZE = 50;

    /** Cache TTL for ID range (seconds). */
    private const RANGE_CACHE_TTL = 10;

    /** Resolved once per process. */
    private static ?int $youtubeCategoryId = null;

    public function __construct(
        private ProviderActionLogService $actionLogService
    ) {}

    public function claim(string $accountIdentity): ?array
    {
        $accountIdentity = trim($accountIdentity);
        if ($accountIdentity === '') {
            return null;
        }

        $categoryId = $this->getYoutubeCategoryId();
        if ($categoryId === null) {
            return null;
        }

        $now = now();

        // Cached ID range — avoids MIN/MAX scan on every request.
        // 10s TTL: new orders appear within 10s, stale range just means
        // a slightly wider random window (harmless).
        $range = $this->getEligibleIdRange($categoryId);
        if ($range === null) {
            return null;
        }

        [$minId, $maxId] = $range;

        // Mix random offsets with low-ID scan so old orders aren't starved.
        // Each attempt: 80% random jump, 20% scan from a low offset.
        for ($attempt = 0; $attempt < self::MAX_RANDOM_ATTEMPTS; $attempt++) {
            $fromId = random_int(1, 5) === 1
                ? random_int($minId, min($minId + 1000, $maxId))  // low range — old orders
                : random_int($minId, $maxId);                      // full range — random

            $result = $this->tryClaimFromOffset($categoryId, $fromId, $now, $accountIdentity);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Load a batch of orders starting from $fromId, filter due, try to claim.
     */
    private function tryClaimFromOffset(int $categoryId, int $fromId, Carbon $now, string $accountIdentity): ?array
    {
        $orders = Order::query()
            ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PENDING])
            ->where('remains', '>', 0)
            ->where('category_id', $categoryId)
            ->where('id', '>=', $fromId)
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        foreach ($orders as $order) {
            $dueAt = $this->computeOrderDueAt($order);
            if ($dueAt !== null && $dueAt->gt($now)) {
                continue;
            }

            $result = $this->tryClaimForOrder($order, $accountIdentity);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Cached MIN/MAX of eligible order IDs. 10s TTL — cheap and avoids full scan.
     *
     * @return array{0: int, 1: int}|null
     */
    private function getEligibleIdRange(int $categoryId): ?array
    {
        return Cache::remember('yt:claim:id_range', self::RANGE_CACHE_TTL, function () use ($categoryId) {
            $range = Order::query()
                ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PENDING])
                ->where('remains', '>', 0)
                ->where('category_id', $categoryId)
                ->selectRaw('MIN(id) as min_id, MAX(id) as max_id')
                ->first();

            if (! $range || ! $range->min_id) {
                return null;
            }

            return [(int) $range->min_id, (int) $range->max_id];
        });
    }

    /**
     * Single youtube category ID — resolved once per process.
     */
    private function getYoutubeCategoryId(): ?int
    {
        if (self::$youtubeCategoryId === null) {
            self::$youtubeCategoryId = (int) \App\Models\Category::query()
                ->where('link_driver', 'youtube')
                ->value('id');
        }

        return self::$youtubeCategoryId ?: null;
    }

    private function computeOrderDueAt(Order $order): ?Carbon
    {
        if ((bool) ($order->dripfeed_enabled ?? false)) {
            $v = $order->dripfeed_next_run_at ?? null;
            if (!$v) {
                return null;
            }

            try {
                return Carbon::parse($v);
            } catch (\Throwable) {
                return null;
            }
        }

        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];
        $nextRunAt = $executionMeta['next_run_at'] ?? null;

        if (!$nextRunAt) {
            return null;
        }

        try {
            return Carbon::parse($nextRunAt);
        } catch (\Throwable) {
            return null;
        }
    }

    private function tryClaimForOrder(Order $order, string $accountIdentity): ?array
    {
        // Keep the service from the outer query — no need to reload it inside the lock.
        $preloadedService = $order->relationLoaded('service') ? $order->service : null;

        return DB::transaction(function () use ($order, $accountIdentity, $preloadedService): ?array {
            // Lock only — order already validated. Just re-check remains under lock.
            $order = Order::query()
                ->where('id', $order->id)
                ->where('remains', '>', 0)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                return null;
            }

            // Use preloaded service if available, otherwise load once.
            if ($preloadedService !== null) {
                $order->setRelation('service', $preloadedService);
            } else {
                $order->loadMissing(['service', 'service.category']);
            }

            $plan = YouTubeExecutionPlanResolver::resolve($order);
            $action = $plan['action'];
            $steps = $plan['steps'];
            $mode = $plan['mode'];
            $perCall = $plan['per_call'];

            $link = trim((string) ($order->link ?? ''));
            if ($link === '') {
                return null;
            }

            $linkHash = YouTubeTargetNormalizer::linkHash($link);

            if (!OrderDripfeedClaimHelper::canClaimTaskNow($order)) {
                return null;
            }

            // In-flight count — uses idx_yt_tasks_order_status index
            $inFlight = YouTubeTask::query()
                ->where('order_id', $order->id)
                ->where('status', YouTubeTask::STATUS_LEASED)
                ->count();

            $target = $order->target_quantity;
            if ((int) $order->delivered + $inFlight >= $target) {
                return null;
            }

            $providerPayload = $order->provider_payload ?? [];
            $youtube = $providerPayload['youtube'] ?? [];
            $parsed = $youtube['parsed'] ?? [];
            $targetHashForLog = $parsed['target_hash'] ?? $linkHash;

//            $actionNames = YouTubeExecutionPlanResolver::stepsToActionNames($steps);
            // TODO: uniqueness check disabled — same account can get same link+action again
            // if ($this->hasStepConflict($accountIdentity, $linkHash, $targetHashForLog, $actionNames)) {
            //     return null;
            // }
//            $actionForLog = $mode === YouTubeExecutionPlanResolver::MODE_COMBO
//                ? YouTubeExecutionPlanResolver::compositeActionForLog($steps)
//                : $action;

            $targetType = null;
            $normalizedTarget = null;
            $targetHashForRow = null;

            $global = null;
            $globalRowCreated = false;
            $previousGlobalState = null;

            // TODO: global target state check disabled — same account can subscribe to same target again
            // $statefulActionForTarget = $this->stepsContainStatefulAction($steps) ? 'subscribe' : null;
            // if ($statefulActionForTarget !== null) {
            //     $norm = YouTubeTargetNormalizer::forSubscribeTarget($order);
            //     $targetType = $norm['target_type'];
            //     $normalizedTarget = $norm['normalized_target'];
            //     $targetHashForRow = $norm['target_hash'];
            //
            //     $global = YouTubeAccountTargetState::query()
            //         ->where('account_identity', $accountIdentity)
            //         ->where('action', $statefulActionForTarget)
            //         ->where('target_hash', $targetHashForRow)
            //         ->lockForUpdate()
            //         ->first();
            //
            //     if ($global !== null) {
            //         if (in_array($global->state, [
            //             YouTubeAccountTargetState::STATE_IN_PROGRESS,
            //             YouTubeAccountTargetState::STATE_SUBSCRIBED,
            //         ], true)) {
            //             return null;
            //         }
            //         $previousGlobalState = $global->state;
            //         $global->update([
            //             'state' => YouTubeAccountTargetState::STATE_IN_PROGRESS,
            //             'last_error' => null,
            //         ]);
            //     } else {
            //         try {
            //             $global = YouTubeAccountTargetState::create([
            //                 'account_identity' => $accountIdentity,
            //                 'action' => $statefulActionForTarget,
            //                 'target_type' => $targetType,
            //                 'normalized_target' => mb_substr($normalizedTarget, 0, 500),
            //                 'target_hash' => $targetHashForRow,
            //                 'state' => YouTubeAccountTargetState::STATE_IN_PROGRESS,
            //             ]);
            //             $globalRowCreated = true;
            //         } catch (UniqueConstraintViolationException) {
            //             return null;
            //         }
            //     }
            // }

            // TODO: duplicate task check disabled — same account can get same order+link+action again
            // if (YouTubeTask::query()
            //     ->where('account_identity', $accountIdentity)
            //     ->where('order_id', $order->id)
            //     ->where('link_hash', $linkHash)
            //     ->where('action', $action)
            //     ->exists()) {
            //     $this->revertGlobalState($global, $globalRowCreated, $previousGlobalState);
            //     return null;
            // }

            $leasedUntil = now()->addSeconds(self::LEASE_TTL_SECONDS);
            $payload = [
                'order_id' => $order->id,
                'per_call' => $perCall,
                'action' => $action,
            ];

            if ($mode === YouTubeExecutionPlanResolver::MODE_COMBO) {
                $payload['mode'] = 'combo';
                $payload['steps'] = $steps;
                $payload['video_target_hash'] = $targetHashForLog;
            }

            $commentForPayload = $this->resolveComment($order, $steps, $action, $inFlight);
            if ($commentForPayload !== null) {
                $payload['comment_text'] = $commentForPayload;
            }

            $task = YouTubeTask::create([
                'order_id' => $order->id,
                'account_identity' => $accountIdentity,
                'action' => $action,
                'link' => $link,
                'link_hash' => $linkHash,
                'target_type' => null,
                'normalized_target' => null,
                'target_hash' => $targetHashForLog,
                'status' => YouTubeTask::STATUS_LEASED,
                'leased_until' => $leasedUntil,
                'payload' => $payload,
            ]);

            // TODO: global state update disabled
            // if ($global !== null) {
            //     $global->update(['last_task_id' => $task->id]);
            // }

            OrderDripfeedClaimHelper::afterTaskClaimed($order);
            $order->update(['status' => Order::STATUS_IN_PROGRESS]);

            $service = $order->service;
            $category = $service?->category;

            $serviceDescription = $service?->description_for_performer ?? '';
            if ($commentForPayload !== null && $commentForPayload !== '') {
                $serviceDescription .= ($serviceDescription !== '' ? "\n" : '') . $commentForPayload;
            }

            $result = [
                'task_id' => $task->id,
                'link' => $link,
                'link_hash' => $linkHash,
                'action' => $action,
                'order_id' => (int) $order->id,
                'target' => null,
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

            if ($mode === YouTubeExecutionPlanResolver::MODE_COMBO) {
                $result['mode'] = 'combo';
                $result['steps'] = $steps;
            }
            if ($commentForPayload !== null && $commentForPayload !== '') {
                $result['comment_text'] = $commentForPayload;
            }

            return $result;
        });
    }

    private function resolveComment(Order $order, array $steps, string $action, int $inFlight): ?string
    {
        $raw = trim((string) ($order->comment_text ?? ''));
        if ($raw === '') {
            return null;
        }

        $isCombo = in_array('comment_custom', $steps, true);
        $isSingleComment = $action === 'comment';

        if (! $isCombo && ! $isSingleComment) {
            return null;
        }

        $comments = array_values(array_filter(array_map('trim', explode("\n", $raw))));
        if (empty($comments)) {
            return $raw;
        }

        $index = (int) $order->delivered + $inFlight;

        if ($isSingleComment && ! $isCombo) {
            return $index < count($comments) ? $comments[$index] : null;
        }

        return $comments[$index % count($comments)];
    }
}
