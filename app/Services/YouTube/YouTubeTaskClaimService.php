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
    private const LEASE_TTL_SECONDS = 3600;

    private const ELIGIBLE_CACHE_TTL = 10;

    private static ?int $youtubeCategoryId = null;

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

        $categoryId = $this->getYoutubeCategoryId();
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
        // This avoids loading and locking orders that will be rejected.
        $due = array_filter($eligible, fn (object $row) => $this->isTimingDue($row, $now));
        if (empty($due)) {
            return null;
        }

        // Priority: smallest remains first, random within equal remains.
        // Older orders naturally have smaller remains → get slight preference.
        $due = array_values($due);
        shuffle($due);
        usort($due, fn ($a, $b) => $a->remains <=> $b->remains);

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
     * Cached eligible orders with only the fields needed for pre-filtering + sorting.
     * Extracts next_run_at from JSON during cache build — no full payload in cache.
     * ~20KB for 1000 rows. 10s TTL in Redis.
     *
     * @return object[] {id, remains, dripfeed_enabled, dripfeed_next_run_at, next_run_at}
     */
    private function getEligibleOrders(int $categoryId): array
    {
        return Cache::remember('yt:claim:eligible', self::ELIGIBLE_CACHE_TTL, function () use ($categoryId) {
            return DB::table('orders')
                ->select('id', 'remains', 'dripfeed_enabled', 'dripfeed_next_run_at', 'provider_payload')
                ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PENDING])
                ->where('remains', '>', 0)
                ->where('category_id', $categoryId)
                ->get()
                ->map(function ($row) {
                    // Extract only next_run_at from JSON — don't cache the full payload
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
     * Uses pre-extracted fields from cache — no JSON parsing per call.
     */
    private function isTimingDue(object $row, Carbon $now): bool
    {
        // Dripfeed timing
        if (! empty($row->dripfeed_enabled) && ! empty($row->dripfeed_next_run_at)) {
            try {
                if (Carbon::parse($row->dripfeed_next_run_at)->gt($now)) {
                    return false;
                }
            } catch (\Throwable) {
            }
        }

        // Speed limit timing (extracted during cache build)
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

    private function getYoutubeCategoryId(): ?int
    {
        if (self::$youtubeCategoryId === null) {
            self::$youtubeCategoryId = (int) \App\Models\Category::query()
                ->where('link_driver', 'youtube')
                ->value('id');
        }

        return self::$youtubeCategoryId ?: null;
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

            // In-flight count
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

            // $actionNames = YouTubeExecutionPlanResolver::stepsToActionNames($steps);
            // TODO: uniqueness check disabled — same account can get same link+action again
            // if ($this->hasStepConflict($accountIdentity, $linkHash, $targetHashForLog, $actionNames)) {
            //     return null;
            // }
            // $actionForLog = $mode === YouTubeExecutionPlanResolver::MODE_COMBO
            //     ? YouTubeExecutionPlanResolver::compositeActionForLog($steps)
            //     : $action;

            $targetType = null;
            $normalizedTarget = null;
            $targetHashForRow = null;

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

            // Build task
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

            // Post-claim: update dripfeed counters
            OrderDripfeedClaimHelper::afterTaskClaimed($order);

            // Post-claim: set speed-limit next_run_at
            $this->setNextRunAt($order);

            $order->update(['status' => Order::STATUS_IN_PROGRESS]);

            // Build response
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

    // =========================================================================
    //  Speed limit
    // =========================================================================

    /**
     * Check if order's speed-limit allows claiming now (execution_meta.next_run_at).
     */
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

    /**
     * After task claimed: set next_run_at based on service speed config.
     * interval = base_interval / speed_multiplier
     *   normal:     base / 1.0
     *   fast:       base / 1.5
     *   super_fast: base / 2.0
     */
    private function setNextRunAt(Order $order): void
    {
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];

        $baseInterval = (int) ($executionMeta['interval_seconds'] ?? 30);
        $speed = (float) ($order->speed_multiplier ?? ($executionMeta['speed_multiplier'] ?? 1));
        $speed = $speed > 0 ? $speed : 1.0;

        $effectiveInterval = (int) max(1, round($baseInterval / $speed));
        $executionMeta['next_run_at'] = now()->addSeconds($effectiveInterval)->toDateTimeString();

        $providerPayload['execution_meta'] = $executionMeta;
        $order->update(['provider_payload' => $providerPayload]);
    }

    // =========================================================================
    //  Comment resolver
    // =========================================================================

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
