<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\TelegramAccountLinkState;
use App\Models\TelegramTask;
use App\Support\TelegramPremiumTemplateScope;
use App\Support\TelegramSystemManagedTemplate;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Push-model pre-assignment job.
 *
 * Runs every 30s per scope. Finds eligible orders, bulk-inserts STATUS_PENDING
 * tasks, and pushes their IDs into per-service Redis queues.
 *
 * /getOrder then does a single LPOP + lightweight UPDATE instead of a full
 * DB transaction with row locks, eliminating deadlocks and thundering-herd load.
 *
 * Design constraints:
 *  - Must run onOneServer() + withoutOverlapping() — no concurrent instances.
 *  - Tasks are inserted WITHOUT account_phone; phone is merged at claim time.
 *  - MAX_PENDING_PER_ORDER caps pre-queued depth so stale tasks stay manageable.
 */
class PreassignTelegramTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout  = 55; // must finish within the 30s schedule window
    public int $tries    = 1;

    // Max pre-queued PENDING+LEASED tasks per order at any moment.
    // Too low → queue drains between runs → accounts fall back to pull model.
    // Too high → expired tasks pile up → cleanup lag.
    // At ~1200 req/s with ~1% claim rate → ~12 claims/sec → need 300+ to cover 30s window.
    private const MAX_PENDING_PER_ORDER = 300;

    // Redis list TTL, refreshed on every push batch.
    private const QUEUE_TTL_SECONDS = 180;

    // Shared eligible-order cache TTL (seconds). Must match TelegramTaskClaimService.
    private const ELIGIBLE_CACHE_TTL = 30;

    // Redis sorted-set TTL for active phone tracking.
    private const ACTIVE_PHONES_TTL = 300;

    public function __construct(
        private readonly string $scope = TelegramPremiumTemplateScope::SCOPE_DEFAULT
    ) {}

    public function handle(): void
    {
        $categoryId = $this->getTelegramCategoryId();
        if (! $categoryId) {
            return;
        }

        $eligible = $this->getEligibleOrders($categoryId);

        // Proactively set no-work keys for services WITHOUT eligible orders.
        // This prevents thundering herd: when no-work expires in the controller,
        // 100K+ accounts can flood in simultaneously. By refreshing the key here
        // every 30s, it never fully expires for idle services.
        $this->refreshNoWorkForIdleServices($categoryId, $eligible);

        if (empty($eligible)) {
            return;
        }

        $now = now();
        $due = array_values(array_filter($eligible, fn(object $r) => $this->isTimingDue($r, $now)));
        if (empty($due)) {
            return;
        }

        $orderIds = array_map(fn($r) => $r->id, $due);

        // How many PENDING+LEASED tasks already exist per order?
        // Avoid inserting beyond MAX_PENDING_PER_ORDER.
        $existingCounts = DB::table('telegram_tasks')
            ->selectRaw('order_id, COUNT(*) as cnt')
            ->whereIn('order_id', $orderIds)
            ->whereIn('status', [TelegramTask::STATUS_PENDING, TelegramTask::STATUS_LEASED])
            ->groupBy('order_id')
            ->pluck('cnt', 'order_id');

        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->where('remains', '>', 0)
            ->with('service')
            ->get()
            ->keyBy('id');

        // Two separate passes:
        //  1. Determine which orders need new tasks inserted (below cap).
        //  2. Push ALL PENDING tasks for ALL due orders to Redis — even orders
        //     already at the cap, so that a Redis flush/TTL expiry never strands
        //     tasks that exist in DB but are absent from the queue.
        $newTasksByService = []; // service_id => [row, ...]  (only newly created)
        $orderIdsByService = []; // service_id => [order_id, ...]  (all due orders)

        foreach ($due as $row) {
            $order = $orders->get($row->id);
            if (! $order || ! $order->service) {
                continue;
            }

            $serviceId = (int) $order->service_id;
            $orderIdsByService[$serviceId][] = $order->id;

            $inflight = (int) ($existingCounts->get($order->id) ?? 0);
            $toCreate = min(
                max(0, min(self::MAX_PENDING_PER_ORDER, (int) $order->remains) - $inflight),
                self::MAX_PENDING_PER_ORDER
            );

            if ($toCreate <= 0) {
                continue; // at cap — skip insert, but order is still in $orderIdsByService
            }

            $action   = $this->resolveAction($order);
            $link     = (string) $order->link;
            $linkHash = TelegramAccountLinkState::linkHash($link);
            $payload  = $this->buildPayload($order, $action, $link, $linkHash);

            for ($i = 0; $i < $toCreate; $i++) {
                $newTasksByService[$serviceId][] = [
                    'id'                  => (string) Str::ulid(),
                    'order_id'            => $order->id,
                    'service_id'          => $serviceId,
                    'subject_type'        => Order::class,
                    'subject_id'          => $order->id,
                    'action'              => $action,
                    'link_hash'           => $linkHash,
                    'telegram_account_id' => null,
                    'provider_account_id' => null,
                    'status'              => TelegramTask::STATUS_PENDING,
                    'leased_until'        => null,
                    'attempt'             => 0,
                    'payload'             => json_encode($payload),
                    'created_at'          => $now->toDateTimeString(),
                    'updated_at'          => $now->toDateTimeString(),
                ];
            }
        }

        if (empty($orderIdsByService)) {
            return;
        }

        $totalQueued = 0;

        foreach ($orderIdsByService as $serviceId => $affectedOrderIds) {
            $queueKey = "tg:service_queue:{$this->scope}:{$serviceId}";

            // Skip re-push if queue already has enough tasks — prevents unbounded
            // growth on idle services (millions of duplicate entries).
            $currentLen = 0;
            try {
                $currentLen = (int) Redis::llen($queueKey);
            } catch (\Throwable) {}

            // Insert new tasks first (if any for this service)
            if (! empty($newTasksByService[$serviceId])) {
                foreach (array_chunk($newTasksByService[$serviceId], 500) as $chunk) {
                    DB::table('telegram_tasks')->insert($chunk);
                }
            }

            // If queue already has enough entries, just refresh TTL and skip re-push.
            // The existing entries are still valid (claimPendingTask checks DB status).
            if ($currentLen >= self::MAX_PENDING_PER_ORDER) {
                try {
                    Redis::expire($queueKey, self::QUEUE_TTL_SECONDS);
                } catch (\Throwable) {}
                continue;
            }

            // Fetch PENDING tasks per-order with a fair share each, then
            // interleave in round-robin so LPOP serves every order evenly.
            //
            // Previously this was a single SELECT ... WHERE IN (orderIds) LIMIT N
            // with no ORDER BY — InnoDB returns rows in PK (ULID) order, so the
            // order whose tasks were inserted first in this run filled the entire
            // queue. Result: one order got ~99% of throughput while peers starved.
            $pushLimit      = self::MAX_PENDING_PER_ORDER - $currentLen;
            $orderCount     = count($affectedOrderIds);
            $perOrderLimit  = (int) ceil($pushLimit / max(1, $orderCount));

            // Shuffle order IDs so the round-robin starting position rotates
            // between runs — removes any first-order bias if totals differ.
            $shuffledOrderIds = $affectedOrderIds;
            shuffle($shuffledOrderIds);

            $byOrder = [];
            foreach ($shuffledOrderIds as $oid) {
                $rows = DB::table('telegram_tasks')
                    ->select('id', 'action')
                    ->where('status', TelegramTask::STATUS_PENDING)
                    ->where('order_id', $oid)
                    ->limit($perOrderLimit)
                    ->get()
                    ->all();

                if (! empty($rows)) {
                    $byOrder[] = $rows;
                }
            }

            if (empty($byOrder)) {
                continue;
            }

            // Round-robin interleave: [o1[0], o2[0], o3[0], o1[1], o2[1], ...]
            $interleaved = [];
            while (count($interleaved) < $pushLimit) {
                $progressed = false;
                foreach ($byOrder as &$rows) {
                    if (! empty($rows)) {
                        $interleaved[] = array_shift($rows);
                        $progressed = true;
                        if (count($interleaved) >= $pushLimit) {
                            break;
                        }
                    }
                }
                unset($rows);
                if (! $progressed) {
                    break;
                }
            }

            if (empty($interleaved)) {
                continue;
            }

            // Pipeline all RPUSHes + EXPIRE in a single round-trip.
            Redis::pipeline(function ($pipe) use ($queueKey, $interleaved) {
                foreach ($interleaved as $row) {
                    $pipe->rpush($queueKey, "{$row->id}:{$row->action}");
                }
                $pipe->expire($queueKey, self::QUEUE_TTL_SECONDS);
            });

            // Clear no_work flag so accounts can pick up tasks without waiting 15s
            try {
                Redis::del("tg:no_work:{$this->scope}:{$serviceId}");
            } catch (\Throwable) {}

            $totalQueued += count($interleaved);
        }

        Log::info('PreassignTelegramTasksJob completed', [
            'scope'        => $this->scope,
            'services'     => count($orderIdsByService),
            'tasks_queued' => $totalQueued,
        ]);
    }

    /**
     * Set no-work keys for all telegram services that have NO eligible orders.
     * Runs every 30s (same as the job), so the key never fully expires for idle
     * services. When orders arrive, the push loop clears the key (Redis::del).
     */
    private function refreshNoWorkForIdleServices(int $categoryId, array $eligible): void
    {
        // All active telegram service IDs for this scope
        $allServiceIds = Cache::remember("tg:preassign:all_svc:{$this->scope}", 300, function () use ($categoryId) {
            $systemManagedKeys = Cache::remember('tg:system_managed_keys', 3600,
                fn() => TelegramSystemManagedTemplate::templateKeys()
            );

            return \App\Models\Service::query()
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->tap(fn($q) => TelegramPremiumTemplateScope::applyServiceTemplateScope($q, $this->scope))
                ->when($systemManagedKeys, fn($q) => $q->whereNotIn('template_key', $systemManagedKeys))
                ->pluck('id')
                ->all();
        });

        if (empty($allServiceIds)) {
            return;
        }

        // Service IDs that DO have eligible orders
        $activeServiceIds = collect($eligible)->pluck('service_id')->unique()->all();

        // Idle = all services minus those with orders
        $idleServiceIds = array_diff($allServiceIds, $activeServiceIds);

        if (empty($idleServiceIds)) {
            return;
        }

        // Pipeline: set no-work for all idle services in one round-trip.
        // TTL 45s — job runs every 30s, so keys are refreshed before expiry.
        try {
            Redis::pipeline(function ($pipe) use ($idleServiceIds) {
                foreach ($idleServiceIds as $sid) {
                    $pipe->set("tg:no_work:{$this->scope}:{$sid}", 1, 'EX', 45, 'NX');
                }
            });
        } catch (\Throwable) {
        }
    }

    // =========================================================================
    //  Helpers (mirrors TelegramTaskClaimService logic — keep in sync)
    // =========================================================================

    private function getTelegramCategoryId(): ?int
    {
        return Cache::remember('tg:category_id', 3600,
            fn() => \App\Models\Category::where('link_driver', 'telegram')->value('id')
        );
    }

    private function getEligibleOrders(int $categoryId): array
    {
        $cacheKey = "tg:preassign:eligible:{$this->scope}";

        return Cache::remember($cacheKey, self::ELIGIBLE_CACHE_TTL, function () use ($categoryId) {
            $systemManagedKeys = Cache::remember('tg:system_managed_keys', 3600,
                fn() => TelegramSystemManagedTemplate::templateKeys()
            );

            $serviceIds = \App\Models\Service::query()
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->where('mode', \App\Models\Service::MODE_MANUAL)
                ->tap(fn($q) => TelegramPremiumTemplateScope::applyServiceTemplateScope($q, $this->scope))
                ->when($systemManagedKeys, fn($q) => $q->whereNotIn('template_key', $systemManagedKeys))
                ->pluck('id')
                ->all();

            if (empty($serviceIds)) {
                return [];
            }

            return DB::table('orders')
                ->select('id', 'service_id', 'remains', 'dripfeed_enabled', 'dripfeed_next_run_at', 'provider_payload')
                ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING])
                ->where('remains', '>', 0)
                ->where('category_id', $categoryId)
                ->whereIn('service_id', $serviceIds)
                ->where(fn($q) => $q->whereNull('execution_phase')->orWhere('execution_phase', Order::EXECUTION_PHASE_RUNNING))
                ->get()
                ->map(fn($row) => (object) [
                    'id'                   => $row->id,
                    'service_id'           => $row->service_id,
                    'remains'              => (int) $row->remains,
                    'dripfeed_enabled'     => $row->dripfeed_enabled,
                    'dripfeed_next_run_at' => $row->dripfeed_next_run_at,
                    'next_run_at'          => json_decode($row->provider_payload ?? '{}', true)['execution_meta']['next_run_at'] ?? null,
                ])
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
            } catch (\Throwable) {}
        }

        if (! empty($row->next_run_at)) {
            try {
                if (Carbon::parse($row->next_run_at)->gt($now)) {
                    return false;
                }
            } catch (\Throwable) {}
        }

        return true;
    }

    private function resolveAction(Order $order): string
    {
        $templateAction = $order->service?->action();
        if ($templateAction !== null && $templateAction !== '') {
            return $templateAction;
        }

        $meta = ($order->provider_payload ?? [])['execution_meta'] ?? [];

        return (string) (is_array($meta) ? ($meta['action'] ?? '') : '') ?: 'subscribe';
    }

    /**
     * Build task payload without account_phone — merged at claim time in
     * TelegramTaskClaimService::claimFromQueue().
     */
    private function buildPayload(Order $order, string $action, string $link, string $linkHash): array
    {
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta   = is_array($providerPayload['execution_meta'] ?? null)
            ? $providerPayload['execution_meta']
            : [];
        $parsed = is_array(($providerPayload['telegram'] ?? [])['parsed'] ?? null)
            ? $providerPayload['telegram']['parsed']
            : [];

        return [
            'link'      => $link,
            'link_hash' => $linkHash,
            'action'    => $action,
            'per_call'  => (int) ($executionMeta['per_call'] ?? 1),
            'meta'      => $executionMeta,
            'parsed'    => $parsed,
            'subject'   => ['type' => 'order', 'id' => $order->id],
            // account_phone intentionally omitted — set at claim time
        ];
    }
}
