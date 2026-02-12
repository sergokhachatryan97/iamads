<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ClientServiceQuota;
use App\Models\TelegramAccount;
use App\Models\TelegramStepEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FlushTelegramStepResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        $streamName    = config('telegram.stream.name', 'tg:step-results');
        $group         = config('telegram.stream.consumer_group', 'flush-workers');
        $batchSize     = (int) config('telegram.stream.batch_size', 1000);
        $blockMs       = (int) config('telegram.stream.block_ms', 1000);
        $consumerName  = 'flush-' . gethostname() . '-' . getmypid();

        // ✅ recovery settings
        $claimIdleMs   = (int) config('telegram.stream.claim_idle_ms', 60_000); // 60s idle => claim
        $claimCount    = (int) config('telegram.stream.claim_count', 500);

        try {
            $this->ensureGroupExists($streamName, $group);

            // 1) Read NEW events
            $newEvents = $this->readNew($streamName, $group, $consumerName, $batchSize, $blockMs);

            // 2) If no new events, try recover stuck events (PEL)
            if (empty($newEvents)) {
                $recovered = $this->autoClaimStuck($streamName, $group, $consumerName, $claimIdleMs, $claimCount);
                if (empty($recovered)) {
                    return;
                }
                $streamEvents = $recovered;
            } else {
                $streamEvents = $newEvents;
            }

            // Parse + aggregate
            [$eventsToInsert, $orderAgg, $quotaAgg, $accountAgg, $eventIds] = $this->parseAndAggregate($streamEvents);

            if (empty($eventsToInsert)) {
                // Nothing meaningful, ack just in case
                $this->ackMany($streamName, $group, $eventIds);
                return;
            }

            // ✅ Process DB updates inside transaction
            DB::transaction(function () use ($eventsToInsert, $orderAgg, $quotaAgg, $accountAgg) {
                // 1) Insert step events log (chunk)
                foreach (array_chunk($eventsToInsert, 500) as $chunk) {
                    TelegramStepEvent::insert($chunk);
                }

                // 2) Bulk update orders
                $this->applyOrderAggregates($orderAgg);

                // 3) Bulk update quotas
                $this->applyQuotaAggregates($quotaAgg);

                // 4) Bulk increment account subscription_count
                $this->applyAccountAggregates($accountAgg);
            });

            // ✅ ACK after successful commit
            $this->ackMany($streamName, $group, $eventIds);

            Log::info('Flushed Telegram step results', [
                'events_count' => count($eventsToInsert),
                'orders_updated' => count($orderAgg),
                'quotas_updated' => count($quotaAgg),
                'accounts_incremented' => count($accountAgg),
                'acked' => count($eventIds),
            ]);
        } catch (\Throwable $e) {
            Log::error('FlushTelegramStepResultsJob failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function ensureGroupExists(string $streamName, string $group): void
    {
        try {
            Redis::xGroup('CREATE', $streamName, $group, '0', true);
        } catch (\Exception $e) {
            if (!str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * Return events array like: [eventId => [field => value...], ...]
     */
    private function readNew(string $streamName, string $group, string $consumer, int $count, int $blockMs): array
    {
        $events = Redis::xReadGroup(
            $group,
            $consumer,
            [$streamName => '>'],
            $count,
            $blockMs
        );

        if (empty($events) || empty($events[$streamName])) {
            return [];
        }

        return $events[$streamName];
    }

    /**
     * Try claim stuck pending messages (PEL).
     * Requires Redis >= 6.2 for XAUTOCLAIM.
     */
    private function autoClaimStuck(string $streamName, string $group, string $consumer, int $minIdleMs, int $count): array
    {
        try {
            // Returns: [next_start_id, [ [id, [field=>val]] , ... ]]
            $res = Redis::command('xautoclaim', [$streamName, $group, $consumer, (string)$minIdleMs, '0-0', 'COUNT', (string)$count]);

            if (!is_array($res) || count($res) < 2) return [];

            $claimed = $res[1] ?? [];
            if (!is_array($claimed) || empty($claimed)) return [];

            $out = [];
            foreach ($claimed as $entry) {
                // entry is [id, [field=>val...]]
                if (!is_array($entry) || count($entry) < 2) continue;
                $id = $entry[0];
                $data = $entry[1] ?? [];
                if (is_string($id) && is_array($data)) {
                    $out[$id] = $data;
                }
            }

            if (!empty($out)) {
                Log::warning('Recovered stuck stream entries (XAUTOCLAIM)', [
                    'count' => count($out),
                    'group' => $group,
                ]);
            }

            return $out;
        } catch (\Throwable $e) {
            // If Redis does not support XAUTOCLAIM, just skip recovery
            Log::debug('XAUTOCLAIM not available or failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Parse stream events and aggregate effects.
     *
     * Returns:
     *  - eventsToInsert: rows for TelegramStepEvent
     *  - orderAgg: [orderId => delivered_inc]
     *  - quotaAgg: [quotaId => qty_left_dec, orders_left_dec_count]
     *  - accountAgg: [accountId => subscription_count_inc]
     *  - eventIds: [id1,id2,...] for ACK
     */
    private function parseAndAggregate(array $streamEvents): array
    {
        $now = now();

        $eventsToInsert = [];
        $orderAgg = [];   // order_id => delivered_inc
        $quotaAgg = [];   // quota_id => ['qty_dec'=>X, 'orders_dec'=>Y]
        $accountAgg = []; // account_id => inc
        $eventIds = [];

        foreach ($streamEvents as $eventId => $data) {
            $eventIds[] = $eventId;

            try {
                $subjectType = $data['subject_type'] ?? null;
                $subjectId   = isset($data['subject_id']) ? (int) $data['subject_id'] : null;

                $accountId   = isset($data['account_id']) ? (int) $data['account_id'] : null;
                $action      = (string) ($data['action'] ?? '');
                $linkHash    = (string) ($data['link_hash'] ?? '');

                $okRaw = $data['ok'] ?? false;
                $ok = ($okRaw === '1' || $okRaw === 'true' || $okRaw === true);

                $error = $data['error'] ?? null;
                $perCall = isset($data['per_call']) ? (int) $data['per_call'] : 1;
                $retryAfter = isset($data['retry_after']) ? (int) $data['retry_after'] : null;

                $performedAt = isset($data['performed_at'])
                    ? Carbon::parse($data['performed_at'])
                    : $now;

                $extra = isset($data['extra']) ? json_decode($data['extra'], true) : null;

                // Log row
                $eventsToInsert[] = [
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'telegram_account_id' => $accountId,
                    'action' => $action,
                    'link_hash' => $linkHash,
                    'ok' => $ok,
                    'error' => $error ? substr((string)$error, 0, 512) : null,
                    'per_call' => $perCall,
                    'retry_after' => $retryAfter,
                    'performed_at' => $performedAt,
                    'extra' => $extra ? json_encode($extra) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Apply aggregates only on ok
                if (!$ok || !$subjectType || !$subjectId) {
                    continue;
                }

                // Orders
                if ($subjectType === Order::class || $subjectType === 'App\\Models\\Order') {
                    $orderAgg[$subjectId] = ($orderAgg[$subjectId] ?? 0) + $perCall;
                }

                // Quotas
                if ($subjectType === ClientServiceQuota::class || $subjectType === 'App\\Models\\ClientServiceQuota') {
                    if (!isset($quotaAgg[$subjectId])) {
                        $quotaAgg[$subjectId] = ['qty_dec' => 0, 'orders_dec' => 0];
                    }
                    $quotaAgg[$subjectId]['qty_dec'] += $perCall;

                    // ⚠️ orders_left-ը քո business rule-ից է կախված։
                    // Եթե ուզում ես ամեն "order completion"-ով նվազի՝ սա այստեղ չդնես։
                    // Եթե ուզում ես ամեն "successful quota step batch"-ով նվազի՝ պահիր։
                    // Այստեղ safe տարբերակ՝ decrement 0, և թող orders_left-ը decrement անես order creation/completion պահին։
                    // $quotaAgg[$subjectId]['orders_dec'] += 1;
                }

                // Accounts subscription_count
                if ($accountId && in_array($action, ['subscribe', 'join', 'follow'], true)) {
                    $accountAgg[$accountId] = ($accountAgg[$accountId] ?? 0) + $perCall;
                }

            } catch (\Throwable $e) {
                Log::error('Failed to parse stream event', [
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [$eventsToInsert, $orderAgg, $quotaAgg, $accountAgg, $eventIds];
    }

    private function applyOrderAggregates(array $orderAgg): void
    {
        if (empty($orderAgg)) return;

        // Bulk load
        $orders = Order::query()
            ->whereIn('id', array_keys($orderAgg))
            ->get()
            ->keyBy('id');

        $completedNow = [];

        foreach ($orderAgg as $orderId => $incDelivered) {
            $order = $orders->get($orderId);
            if (!$order) continue;

            $oldStatus = $order->status;

            $newDelivered = min((int)$order->quantity, (int)$order->delivered + (int)$incDelivered);
            $newRemains = max(0, (int)$order->quantity - $newDelivered);
            $newStatus = $newRemains > 0 ? Order::STATUS_IN_PROGRESS : Order::STATUS_COMPLETED;

            $order->update([
                'delivered' => $newDelivered,
                'remains' => $newRemains,
                'status' => $newStatus,
            ]);

            if ($newStatus === Order::STATUS_COMPLETED && $oldStatus !== Order::STATUS_COMPLETED) {
                $completedNow[] = $orderId;
            }
        }

        // Dependency unblock (bulk)
        if (!empty($completedNow)) {
            $dependentOrders = Order::query()
                ->whereIn('depends_on_order_id', $completedNow)
                ->where('status', Order::STATUS_PENDING_DEPENDENCY)
                ->get();

            foreach ($dependentOrders as $dep) {
                $dep->updateDependencyStatus();

                if ($dep->isDependencySatisfied()) {
                    $dep->update([
                        'status' => Order::STATUS_VALIDATING,
                        'depends_verified_at' => now(),
                    ]);

                    \App\Jobs\InspectTelegramLinkJob::dispatch($dep->id)->afterCommit();
                }
            }
        }
    }

    private function applyQuotaAggregates(array $quotaAgg): void
    {
        if (empty($quotaAgg)) return;

        $quotas = ClientServiceQuota::query()
            ->whereIn('id', array_keys($quotaAgg))
            ->get()
            ->keyBy('id');

        foreach ($quotaAgg as $quotaId => $agg) {
            $quota = $quotas->get($quotaId);
            if (!$quota) continue;

            $qtyDec = (int) ($agg['qty_dec'] ?? 0);
            $ordersDec = (int) ($agg['orders_dec'] ?? 0);

            $newQuantityLeft = max(0, (int)($quota->quantity_left ?? 0) - $qtyDec);

            $newOrdersLeft = $quota->orders_left !== null
                ? max(0, (int)($quota->orders_left ?? 0) - $ordersDec)
                : null;

            $quota->update([
                'quantity_left' => $newQuantityLeft,
                'orders_left' => $newOrdersLeft,
            ]);
        }
    }

    private function applyAccountAggregates(array $accountAgg): void
    {
        if (empty($accountAgg)) return;

        foreach ($accountAgg as $accountId => $inc) {
            TelegramAccount::query()
                ->whereKey((int)$accountId)
                ->increment('subscription_count', (int)$inc);
        }
    }

    private function ackMany(string $streamName, string $group, array $eventIds): void
    {
        if (empty($eventIds)) return;

        foreach (array_chunk($eventIds, 200) as $chunk) {
            Redis::xAck($streamName, $group, ...$chunk);
        }
    }
}
