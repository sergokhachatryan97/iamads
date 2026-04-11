<?php

namespace App\Services\YouTube;

use App\Models\Order;
use App\Models\YouTubeTask;
use App\Services\ProviderActionLogService;
use App\Support\Performer\ClaimConcurrencyLimiter;
use App\Support\Performer\OrderDripfeedClaimHelper;
use App\Support\YouTube\YouTubeTargetNormalizer;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class YouTubeTaskClaimService
{
    /** How long a leased task is reserved for the performer before the cleanup job fails it. */
    private const LEASE_TTL_SECONDS = 1800;

    /** TTL for the cached eligible-orders pool (shared across all performers). */
    private const ELIGIBLE_CACHE_TTL = 10;

    /** Per-account cooldown between watch-time task claims (one task = one chunk). */
    private const WATCH_CHUNK_SECONDS = 15;

    /**
     * After this many seconds without another watch task, the per-account
     * "recently watched" bias decays and watch orders become eligible again.
     * Larger value = more non-watch tasks between two watch tasks per account.
     */
    private const WATCH_BIAS_WINDOW_SECONDS = 60;

    /** Number of orders to bulk-load per claim attempt batch. */
    private const ORDER_BATCH_SIZE = 50;

    private static ?int $youtubeCategoryId = null;

    public function __construct(
        private ProviderActionLogService $actionLogService
    ) {}

    // =========================================================================
    //  Public API
    // =========================================================================

    /**
     * @return array|null Task payload, error array ['error' => ..., 'retry_after' => ...], or null
     */
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

    /**
     * @return array|null Task payload, error array ['error' => ..., 'retry_after' => ...], or null
     */
    private function claimInner(string $accountIdentity): ?array
    {
        // Watch-time cooldown: block ALL claims if this account has an active watch
        // task created < WATCH_CHUNK_SECONDS ago. Business rule: a new task can
        // only be claimed after the current watch task is completed.
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

        // Per-account recently-watched bias: even after the cooldown lifts, if this
        // account had a watch task in the last WATCH_BIAS_WINDOW_SECONDS, prefer
        // non-watch orders for this claim. The fairness sort applies a soft progress
        // penalty to watch orders so the account naturally interleaves watch with
        // other action types instead of getting watch tasks back-to-back.
        //
        // The bias is soft: if no non-watch order is available, watch can still win
        // (the penalty just pushes it lower, doesn't exclude it). Cheap query that
        // uses the (account_identity, action, status, created_at) index.
        $accountRecentlyWatched = YouTubeTask::query()
            ->where('account_identity', $accountIdentity)
            ->where('action', 'watch')
            ->where('created_at', '>=', now()->subSeconds(self::WATCH_BIAS_WINDOW_SECONDS))
            ->exists();

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

        // Fairness sort.
        //
        // Goal: pick the order with the lowest *effective progress* — i.e. the one
        // furthest from completion, counting both already-delivered units and the
        // units that are currently in-flight (leased). This makes every order
        // advance together regardless of size or age and prevents one order from
        // hogging the queue.
        //
        // For non-watch orders this is straightforward:
        //
        //     progress = (delivered + leased) / target_quantity
        //
        // Watch orders need a unit conversion. Their `delivered` field counts
        // viewers, but `leased` counts 15-second sub-tasks (one viewer requires
        // tasks_per_unit = ceil(watch_time/15) sub-tasks). To compare apples to
        // apples we restate everything in sub-task units:
        //
        //     watch_progress = (delivered * tasks_per_unit + leased)
        //                       / (target_quantity * tasks_per_unit)
        //
        // Both formulas live in [0, 1] and are directly comparable across order
        // types. A previous version of this sort dropped `leased` entirely for
        // watch orders, which made watch orders look "less progressed" than every
        // non-watch order with in-flight tasks and caused them to be picked on
        // every request — that's the bug this version fixes.
        //
        // Tie-break order:
        //   1. lowest effective progress (primary fairness signal)
        //   2. lowest leased count       (within an equal-progress group, prefer
        //                                 orders currently doing less in-flight work)
        //   3. random jitter             (so multiple equal-progress / equal-leased
        //                                 orders rotate fairly across requests)
        //
        // Important: this entire block is a *priority hint*. All correctness checks
        // (cap, dripfeed, speed-limit, hasStepConflict, watch-time cap) re-run inside
        // tryClaimForOrder under lockForUpdate. Wrong sort values can only affect
        // ordering, never correctness.
        $due = array_values($due);
        $dueIds = array_map(fn ($r) => $r->id, $due);

        $leasedByOrder = DB::table('youtube_tasks')
            ->select('order_id', DB::raw('COUNT(*) as cnt'))
            ->whereIn('order_id', $dueIds)
            ->where('status', YouTubeTask::STATUS_LEASED)
            ->groupBy('order_id')
            ->pluck('cnt', 'order_id')
            ->all();

        foreach ($due as $row) {
            $leased = (int) ($leasedByOrder[$row->id] ?? 0);
            $target = max(1, (int) ($row->target_quantity ?? 0));
            $delivered = (int) ($row->delivered ?? 0);
            $isWatch = ($row->action ?? null) === 'watch';

            if ($isWatch) {
                $watchTimeSeconds = (int) ($row->watch_time_seconds ?? 0);
                $tasksPerUnit = $watchTimeSeconds >= self::WATCH_CHUNK_SECONDS
                    ? (int) ceil($watchTimeSeconds / self::WATCH_CHUNK_SECONDS)
                    : 1;
                $totalSubtasks = max(1, $tasksPerUnit * $target);
                $deliveredSubtasks = $delivered * $tasksPerUnit;
                $progress = ($deliveredSubtasks + $leased) / $totalSubtasks;
            } else {
                $progress = ($delivered + $leased) / $target;
            }

            $progress = min(1.0, max(0.0, $progress));

            // Per-account recently-watched bias: push watch orders to the back of
            // the queue for this claim so the account gets a non-watch task instead.
            // +0.5 is a "soft" demote — a watch order at true progress 0.30 sorts as
            // if it were at 0.80, beating any non-watch order over 0.80 but losing
            // to any non-watch order under 0.80. If no non-watch order is available,
            // the bias has no effect (watch is still the best of what's left).
            if ($accountRecentlyWatched && $isWatch) {
                $progress = min(1.0, $progress + 0.5);
            }

            // Quantize to basis points so float noise doesn't defeat the leased
            // tie-breaker for orders that should be considered "equal progress".
            $row->_progress = (int) round($progress * 10000);
            $row->_leased = $leased;
            $row->_jitter = random_int(0, PHP_INT_MAX);
        }

        usort($due, function (object $a, object $b) {
            return [$a->_progress, $a->_leased, $a->_jitter]
                <=> [$b->_progress, $b->_leased, $b->_jitter];
        });

        // Load full models in batches, try to claim in fairness order.
        foreach (array_chunk($due, self::ORDER_BATCH_SIZE) as $batch) {
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

    /** Cache key for the shared eligible-orders pool. */
    private const ELIGIBLE_CACHE_KEY = 'yt:claim:eligible';

    /** Lock TTL — auto-released after this many seconds even if the holder dies. */
    private const ELIGIBLE_LOCK_TTL = 10;

    /** Max seconds a waiter will block for the cache-refresh lock before giving up. */
    private const ELIGIBLE_LOCK_WAIT = 3;

    /**
     * Cached eligible orders with only the fields needed for pre-filtering + fairness sorting.
     * Extracts next_run_at and action from JSON during cache build — no full payload in cache.
     * ~25KB for 1000 rows. 10s TTL in Redis.
     *
     * delivered + target_quantity are included so the fairness sort can compute
     * effective progress without a second query per order.
     *
     * action is extracted so the fairness sort can detect watch-time orders, which
     * need a different progress formula (their `delivered` is in viewers, not in
     * tasks, so the standard formula would inflate their progress and unfairly
     * deprioritize them).
     *
     * Stampede protection: when the cache expires under high concurrency, all polling
     * performers would otherwise miss simultaneously and run the same SELECT in parallel,
     * exhausting the DB connection pool. We serialize the rebuild with Cache::lock so
     * only one process queries — everyone else waits briefly and reads the freshly
     * populated value. The lock has a generous TTL and a short waiter timeout: if the
     * lock somehow can't be acquired in time, we fall through to a direct query rather
     * than failing the request.
     *
     * @return object[] {id, remains, delivered, target_quantity, dripfeed_enabled,
     *                   dripfeed_next_run_at, next_run_at, action}
     */
    private function getEligibleOrders(int $categoryId): array
    {
        // Fast path — cache hit. The vast majority of claim requests return here
        // without ever taking the lock or hitting the database.
        $cached = Cache::get(self::ELIGIBLE_CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        // Cache miss — try to be the single process that refreshes it.
        try {
            return Cache::lock(self::ELIGIBLE_CACHE_KEY . ':refresh', self::ELIGIBLE_LOCK_TTL)
                ->block(self::ELIGIBLE_LOCK_WAIT, function () use ($categoryId) {
                    // Re-check inside the lock: another process may have just
                    // refreshed the cache while we were waiting.
                    $cached = Cache::get(self::ELIGIBLE_CACHE_KEY);
                    if ($cached !== null) {
                        return $cached;
                    }

                    $fresh = $this->loadEligibleOrders($categoryId);
                    Cache::put(self::ELIGIBLE_CACHE_KEY, $fresh, self::ELIGIBLE_CACHE_TTL);

                    return $fresh;
                });
        } catch (LockTimeoutException) {
            // Couldn't get the lock within the wait window. Fall through to a
            // direct (uncached) query — better to serve a slow request than to
            // fail it. In practice this branch should rarely fire.
            return $this->loadEligibleOrders($categoryId);
        }
    }

    /**
     * Load the eligible-orders pool directly from the database (no cache).
     * Extracted so both the cache-refresh path and the lock-timeout fallback
     * can share the same query + mapping logic.
     *
     * Note on `target_quantity`: there is NO `target_quantity` column on the orders
     * table — it's an Eloquent accessor on App\Models\Order that computes
     * `quantity * (1 + service.overflow_percent / 100)`. We replicate that formula
     * here in the raw query builder by LEFT JOINing services and computing the
     * target in PHP, so the cached objects expose the same `target_quantity` shape
     * the rest of the service expects.
     *
     * @return object[]
     */
    private function loadEligibleOrders(int $categoryId): array
    {
        return DB::table('orders')
            ->leftJoin('services', 'services.id', '=', 'orders.service_id')
            ->select(
                'orders.id',
                'orders.remains',
                'orders.delivered',
                'orders.quantity',
                'orders.dripfeed_enabled',
                'orders.dripfeed_next_run_at',
                'orders.provider_payload',
                'services.overflow_percent'
            )
            ->whereIn('orders.status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PENDING])
            ->where('orders.remains', '>', 0)
            ->where('orders.category_id', $categoryId)
            ->get()
            ->map(function ($row) {
                // Extract next_run_at, action, and watch_time_seconds from JSON once
                // during cache build — avoids re-decoding the full payload on every
                // claim request. watch_time_seconds is needed by the fairness sort to
                // convert watch-order progress into the same sub-task unit as the
                // standard formula, so watch orders don't dominate the queue.
                $nextRunAt = null;
                $action = null;
                $watchTimeSeconds = 0;
                if (is_string($row->provider_payload)) {
                    $payload = json_decode($row->provider_payload, true);
                    $nextRunAt = $payload['execution_meta']['next_run_at'] ?? null;
                    $action = $payload['execution_meta']['action'] ?? null;
                    $watchTimeSeconds = (int) ($payload['execution_meta']['watch_time_seconds'] ?? 0);
                }

                // Compute target_quantity the same way Order::getTargetQuantityAttribute does:
                // ceil(quantity * (1 + overflow_percent/100)). Falls back to plain quantity
                // when overflow is zero or unknown (e.g. service row is missing).
                $quantity = (int) $row->quantity;
                $overflowPercent = (float) ($row->overflow_percent ?? 0);
                $targetQuantity = $overflowPercent > 0
                    ? (int) ceil($quantity * (1 + $overflowPercent / 100))
                    : $quantity;

                return (object) [
                    'id' => $row->id,
                    'remains' => (int) $row->remains,
                    'delivered' => (int) $row->delivered,
                    'target_quantity' => $targetQuantity,
                    'dripfeed_enabled' => $row->dripfeed_enabled,
                    'dripfeed_next_run_at' => $row->dripfeed_next_run_at,
                    'next_run_at' => $nextRunAt,
                    'action' => $action,
                    'watch_time_seconds' => $watchTimeSeconds,
                ];
            })
            ->all();
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
            // 1. Re-fetch the order under lock so two concurrent claims serialize.
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

            // 2. Authoritative dripfeed and speed-limit gates (under lock).
            if (! OrderDripfeedClaimHelper::canClaimTaskNow($order)) {
                return null;
            }
            if (! $this->canClaimBySpeedLimit($order)) {
                return null;
            }

            // 3. Resolve execution plan + validate link.
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

            // 4. Watch-time vs standard cap. Watch-time has its own cap (LEASED + DONE
            //    sub-task count vs total_tasks_needed) and overrides per_call to 0.
            $isWatchTime = $action === 'watch';
            $watchTimeMeta = null;
            $inFlight = 0;

            if ($isWatchTime) {
                $watchTimeMeta = $this->buildWatchTimeMeta($order);
                if ($this->exceedsWatchTimeCap($order, (int) $watchTimeMeta['total_tasks'])) {
                    return null;
                }
                // Watch-time: delivery is recomputed from DONE count, not incremented per task.
                $perCall = 0;
            } else {
                // Standard cap requires the live LEASED count for this order.
                // Skipped for watch-time because (a) the watch-time cap above already
                // covered it, and (b) `inFlight` is otherwise only used by resolveComment()
                // which never triggers for the `watch` action.
                $inFlight = YouTubeTask::query()
                    ->where('order_id', $order->id)
                    ->where('status', YouTubeTask::STATUS_LEASED)
                    ->count();

                if ((int) $order->delivered + $inFlight >= (int) $order->target_quantity) {
                    return null;
                }
            }

            // 5. Resolve target hash for action-log uniqueness.
            $providerPayload = $order->provider_payload ?? [];
            $parsed = $providerPayload['youtube']['parsed'] ?? [];
            $targetHashForLog = $parsed['target_hash'] ?? $linkHash;

            // 6. Uniqueness gate: (account + action + link) must be unique across the system.
            $actionNames = YouTubeExecutionPlanResolver::stepsToActionNames($steps);
            if ($this->hasStepConflict($accountIdentity, $linkHash, $targetHashForLog, $actionNames)) {
                return null;
            }

            // 7. Build task payload.
            $commentForPayload = $this->resolveComment($order, $steps, $action, $inFlight);
            $payload = $this->buildTaskPayload(
                $order,
                $action,
                $perCall,
                $mode,
                $steps,
                $watchTimeMeta,
                $targetHashForLog,
                $commentForPayload
            );

            // 8. INSERT the task row (this is the actual "task generation" moment).
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
                'leased_until' => now()->addSeconds(self::LEASE_TTL_SECONDS),
                'payload' => $payload,
            ]);

            // 9. Single merged UPDATE on the order: status + dripfeed counters
            //    + speed-limit next_run_at — so we touch the locked row exactly once.
            $orderUpdates = ['status' => Order::STATUS_IN_PROGRESS];
            $orderUpdates += OrderDripfeedClaimHelper::computeAfterTaskClaimedUpdates($order);
            $orderUpdates['provider_payload'] = $this->buildProviderPayloadWithNextRunAt($order);
            $order->update($orderUpdates);

            // 10. Build the response payload for the performer.
            return $this->buildClaimResponse(
                $task,
                $order,
                $action,
                $link,
                $linkHash,
                $mode,
                $steps,
                $watchTimeMeta,
                $commentForPayload
            );
        });
    }

    /**
     * Build the watch_time meta block for a watch-time task.
     * Caller must already know the order is watch-time. Reads `watch_time_seconds`
     * from execution_meta with a 30-second floor (legacy default).
     *
     * @return array{watch_time_seconds:int, tasks_per_unit:int, total_tasks:int, chunk_seconds:int}
     */
    private function buildWatchTimeMeta(Order $order): array
    {
        $execMeta = $this->getExecutionMeta($order);
        $watchTimeSeconds = (int) ($execMeta['watch_time_seconds'] ?? 0);
        if ($watchTimeSeconds < self::WATCH_CHUNK_SECONDS) {
            $watchTimeSeconds = 30;
        }

        $tasksPerUnit = (int) ceil($watchTimeSeconds / self::WATCH_CHUNK_SECONDS);

        return [
            'watch_time_seconds' => $watchTimeSeconds,
            'tasks_per_unit' => $tasksPerUnit,
            'total_tasks' => $tasksPerUnit * (int) $order->target_quantity,
            'chunk_seconds' => self::WATCH_CHUNK_SECONDS,
        ];
    }

    /**
     * Watch-time cap: LEASED + DONE sub-task count must stay below total_tasks_needed.
     * FAILED tasks are excluded so an expired lease frees its slot.
     */
    private function exceedsWatchTimeCap(Order $order, int $totalTasksNeeded): bool
    {
        $existingTasks = YouTubeTask::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [YouTubeTask::STATUS_LEASED, YouTubeTask::STATUS_DONE])
            ->count();

        return $existingTasks >= $totalTasksNeeded;
    }

    /**
     * Assemble the JSON payload stored on the youtube_tasks row.
     *
     * @param  array<string>  $steps
     * @param  array<string,mixed>|null  $watchTimeMeta
     * @return array<string,mixed>
     */
    private function buildTaskPayload(
        Order $order,
        string $action,
        int $perCall,
        string $mode,
        array $steps,
        ?array $watchTimeMeta,
        string $targetHashForLog,
        ?string $commentForPayload
    ): array {
        $payload = [
            'order_id' => $order->id,
            'per_call' => $perCall,
            'action' => $action,
        ];

        if ($watchTimeMeta !== null) {
            $payload['watch_time'] = $watchTimeMeta;
        }

        if ($mode === YouTubeExecutionPlanResolver::MODE_COMBO) {
            $payload['mode'] = 'combo';
            $payload['steps'] = $steps;
            $payload['video_target_hash'] = $targetHashForLog;
        }

        if ($commentForPayload !== null) {
            $payload['comment_text'] = $commentForPayload;
        }

        return $payload;
    }

    /**
     * Build the response array returned to the performer client.
     *
     * @param  array<string>  $steps
     * @param  array<string,mixed>|null  $watchTimeMeta
     * @return array<string,mixed>
     */
    private function buildClaimResponse(
        YouTubeTask $task,
        Order $order,
        string $action,
        string $link,
        string $linkHash,
        string $mode,
        array $steps,
        ?array $watchTimeMeta,
        ?string $commentForPayload
    ): array {
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

        if ($watchTimeMeta !== null) {
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
    }

    /**
     * Safely extract `provider_payload.execution_meta` as an array. Returns [] if
     * the payload is missing or malformed.
     *
     * @return array<string,mixed>
     */
    private function getExecutionMeta(Order $order): array
    {
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = $providerPayload['execution_meta'] ?? null;

        return is_array($executionMeta) ? $executionMeta : [];
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
        $nextRunAt = $this->getExecutionMeta($order)['next_run_at'] ?? null;

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

//        $intervalSeconds = (int) ($executionMeta['interval_seconds'] ?? 1);
        $intervalSeconds = 5;
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
