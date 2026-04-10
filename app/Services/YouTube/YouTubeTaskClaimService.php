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
    private const LEASE_TTL_SECONDS = 1800;

    private const ELIGIBLE_CACHE_TTL = 10;

    private static ?int $youtubeCategoryId = null;

    public function __construct(
        private ProviderActionLogService $actionLogService
    ) {}

    // =========================================================================
    //  Public API
    // =========================================================================

    private const WATCH_CHUNK_SECONDS = 15;

    /**
     * @return array|null Task payload, error array ['error' => ..., 'retry_after' => ...], or null
     */
    public function claim(string $accountIdentity): ?array
    {
        $accountIdentity = trim($accountIdentity);
        if ($accountIdentity === '') {
            return null;
        }

        // Watch-time cooldown: block if account has an active watch task created < 15s ago
        $recentWatchTask = YouTubeTask::query()
            ->where('account_identity', $accountIdentity)
            ->where('action', 'watch')
            ->where('status', YouTubeTask::STATUS_LEASED)
            ->where('created_at', '>=', now()->subSeconds(self::WATCH_CHUNK_SECONDS))
            ->first();

        if ($recentWatchTask) {
            $elapsed = now()->diffInSeconds($recentWatchTask->created_at);
            $retryAfter = max(1, self::WATCH_CHUNK_SECONDS - $elapsed);

            return [
                'error' => 'Watch time not yet completed. Please wait before requesting a new task.',
                'retry_after' => $retryAfter,
            ];
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

        // Fair distribution: pick orders with the fewest currently-leased tasks first,
        // so every order makes parallel progress instead of one order eating all slots.
        // Random tie-break keeps things even when many orders have the same in-flight count.
        $due = array_values($due);
        $dueIds = array_map(fn ($r) => $r->id, $due);

        $leasedByOrder = DB::table('youtube_tasks')
            ->select('order_id', DB::raw('COUNT(*) as cnt'))
            ->whereIn('order_id', $dueIds)
            ->where('status', YouTubeTask::STATUS_LEASED)
            ->groupBy('order_id')
            ->pluck('cnt', 'order_id')
            ->all();

        // Attach a small random jitter so equal-count orders don't always sort the same way.
        foreach ($due as $row) {
            $row->_leased = (int) ($leasedByOrder[$row->id] ?? 0);
            $row->_jitter = random_int(0, PHP_INT_MAX);
        }

        usort($due, function (object $a, object $b) {
            return [$a->_leased, $a->_jitter] <=> [$b->_leased, $b->_jitter];
        });

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

            // Watch-time orders: each task = 15s, delivered increments per watch_time/15 tasks
            $isWatchTime = $action === 'watch';
            $watchTimeMeta = null;
            if ($isWatchTime) {
                $provPayload = $order->provider_payload ?? [];
                $execMeta = is_array($provPayload['execution_meta'] ?? null) ? $provPayload['execution_meta'] : [];
                $watchTimeSeconds = (int) ($execMeta['watch_time_seconds'] ?? 0);
                if ($watchTimeSeconds < 15) {
                    $watchTimeSeconds = 30;
                }

                $tasksPerUnit = (int) ceil($watchTimeSeconds / 15);
                $totalTasksNeeded = $tasksPerUnit * $order->target_quantity;

                $watchTimeMeta = [
                    'watch_time_seconds' => $watchTimeSeconds,
                    'tasks_per_unit' => $tasksPerUnit,
                    'total_tasks' => $totalTasksNeeded,
                    'chunk_seconds' => 15,
                ];

                // For watch-time: cap by total tasks, not by delivered+remains
                $existingTasks = YouTubeTask::query()
                    ->where('order_id', $order->id)
                    ->whereIn('status', [YouTubeTask::STATUS_LEASED, YouTubeTask::STATUS_DONE])
                    ->count();

                if ($existingTasks >= $totalTasksNeeded) {
                    return null;
                }

                // Override per_call to 0 — delivery is calculated from done task count
                $perCall = 0;
            }

                // Standard in-flight check
                $inFlight = YouTubeTask::query()
                    ->where('order_id', $order->id)
                    ->where('status', YouTubeTask::STATUS_LEASED)
                    ->count();

            if (! $isWatchTime) {
                $target = $order->target_quantity;
                if ((int) $order->delivered + $inFlight >= $target) {
                    return null;
                }
            }

            $providerPayload = $order->provider_payload ?? [];
            $youtube = $providerPayload['youtube'] ?? [];
            $parsed = $youtube['parsed'] ?? [];
            $targetHashForLog = $parsed['target_hash'] ?? $linkHash;

            // Uniqueness: account + action + link must be unique across the system.
            // Blocks if any requested step is already in-flight or already performed for this account+target.
            $actionNames = YouTubeExecutionPlanResolver::stepsToActionNames($steps);
            if ($this->hasStepConflict($accountIdentity, $linkHash, $targetHashForLog, $actionNames)) {
                return null;
            }

            // Build task
            $leasedUntil = now()->addSeconds(self::LEASE_TTL_SECONDS);
            $payload = [
                'order_id' => $order->id,
                'per_call' => $perCall,
                'action' => $action,
            ];

            if ($isWatchTime && $watchTimeMeta !== null) {
                $payload['watch_time'] = $watchTimeMeta;
            }

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

            // Post-claim: merge dripfeed counters, speed-limit next_run_at, and status
            // into ONE UPDATE so we touch the locked order row a single time.
            $orderUpdates = ['status' => Order::STATUS_IN_PROGRESS];
            $orderUpdates += OrderDripfeedClaimHelper::computeAfterTaskClaimedUpdates($order);
            $orderUpdates['provider_payload'] = $this->buildProviderPayloadWithNextRunAt($order);

            $order->update($orderUpdates);

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

            if ($isWatchTime && $watchTimeMeta !== null) {
                $result['watch_time_seconds'] = $watchTimeMeta['chunk_seconds'];
            }

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
    //  Uniqueness (account + action + link)
    // =========================================================================

    /**
     * Returns true if any of the requested action steps would conflict with:
     *   1. An in-flight task (LEASED/PENDING) for the same account+link covering the same action(s).
     *   2. A previously recorded successful action in provider_action_logs (account+target+action).
     *   3. An exact composite combo already performed for this account+target.
     *
     * Mirrors the Telegram-style "account+action+link must be unique" guarantee.
     *
     * @param  array<string>  $actionNames  Resolved per-step action names (subscribe|view|react|comment).
     */
    private function hasStepConflict(
        string $accountIdentity,
        string $linkHash,
        string $videoTargetHash,
        array $actionNames
    ): bool {
        if (empty($actionNames)) {
            return false;
        }

        // 1. Active tasks: single tasks with overlapping action, or combo tasks with overlapping steps
        $activeTasks = YouTubeTask::query()
            ->where('account_identity', $accountIdentity)
            ->where('link_hash', $linkHash)
            ->where('status', YouTubeTask::STATUS_LEASED)
            ->get(['id', 'action', 'payload']);

        foreach ($activeTasks as $task) {
            if (in_array($task->action, $actionNames, true)) {
                return true;
            }
            if ($task->action === 'combo') {
                $payload = $task->payload;
                $steps = is_array($payload) ? ($payload['steps'] ?? []) : [];
                $existingNames = YouTubeExecutionPlanResolver::stepsToActionNames($steps);
                if (array_intersect($actionNames, $existingNames) !== []) {
                    return true;
                }
            }
        }

        // 2. Performed actions: any requested step OR the exact composite combo
        //    already recorded for this account+target. One query covers both.
        $logActions = $actionNames;
        if (count($actionNames) > 1) {
            $logActions[] = YouTubeExecutionPlanResolver::compositeActionForLog($actionNames);
        }

        return $this->actionLogService->hasPerformedAny(
            ProviderActionLogService::PROVIDER_YOUTUBE,
            $accountIdentity,
            $videoTargetHash,
            $logActions
        );
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
     * Build the provider_payload value with execution_meta.next_run_at advanced by the
     * pre-calculated interval. Returns the merged payload — caller persists it as part
     * of a single combined UPDATE.
     *
     * @return array<string, mixed>
     */
    private function buildProviderPayloadWithNextRunAt(Order $order): array
    {
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];

        $intervalSeconds = (int) ($executionMeta['interval_seconds'] ?? 30);
        $executionMeta['next_run_at'] = now()->addSeconds(max(1, $intervalSeconds))->toDateTimeString();

        $providerPayload['execution_meta'] = $executionMeta;

        return $providerPayload;
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
