<?php

namespace App\Jobs;

use App\Models\TelegramAccountLinkState;
use App\Models\TelegramOrderMembership;
use App\Models\TelegramTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fails Telegram tasks stuck in LEASED status past their lease expiry (5+ minutes).
 * Cleans up related TelegramAccountLinkState and TelegramOrderMembership records.
 *
 * Optimized to avoid max_user_connections:
 *  - Batches expired tasks (BATCH_SIZE) to limit memory and per-iteration locks.
 *  - Bulk SQL updates instead of per-row Eloquent saves.
 *  - Single transaction per batch (not per task).
 */
class CleanExpiredTelegramTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    private const GRACE_MINUTES = 5;

    private const BATCH_SIZE = 200;

    private const MAX_BATCHES = 50;

    public function handle(): void
    {
        $cutoff = now()->subMinutes(self::GRACE_MINUTES);

        $totalFailed = 0;
        $totalLinkStates = 0;
        $totalMemberships = 0;

        for ($i = 0; $i < self::MAX_BATCHES; $i++) {
            $batch = DB::table('telegram_tasks')
                ->select('id', 'order_id', 'link_hash', 'action', 'telegram_account_id', 'payload')
                ->where('status', TelegramTask::STATUS_LEASED)
                ->where(function ($q) use ($cutoff) {
                    $q->where(function ($q2) use ($cutoff) {
                        $q2->whereNotNull('leased_until')
                            ->where('leased_until', '<', $cutoff);
                    })->orWhere(function ($q2) use ($cutoff) {
                        $q2->whereNull('leased_until')
                            ->where('updated_at', '<', $cutoff);
                    });
                })
                ->limit(self::BATCH_SIZE)
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            $taskIds = $batch->pluck('id')->all();

            // Group by (phone, link_hash, action) so we can issue one bulk update
            // per group instead of one per task.
            $linkStateGroups = [];
            // Group by (order_id) — we need order_id + task_id for the membership lookup
            $membershipPairs = [];

            foreach ($batch as $task) {
                $payload = is_string($task->payload) ? json_decode($task->payload, true) : ($task->payload ?? []);
                $phone = $payload['account_phone'] ?? $task->telegram_account_id;
                $action = $task->action ?? 'subscribe';

                if ($phone && $task->link_hash) {
                    $key = $phone.'|'.$task->link_hash.'|'.$action;
                    if (! isset($linkStateGroups[$key])) {
                        $linkStateGroups[$key] = [
                            'phone' => $phone,
                            'link_hash' => $task->link_hash,
                            'action' => $action,
                        ];
                    }
                }

                if ($task->order_id) {
                    $membershipPairs[] = ['order_id' => $task->order_id, 'task_id' => $task->id];
                }
            }

            $batchFailed = 0;
            $batchLinkStates = 0;
            $batchMemberships = 0;

            DB::transaction(function () use ($taskIds, $linkStateGroups, $membershipPairs, &$batchFailed, &$batchLinkStates, &$batchMemberships) {
                // Bulk fail tasks
                $batchFailed = DB::table('telegram_tasks')
                    ->whereIn('id', $taskIds)
                    ->where('status', TelegramTask::STATUS_LEASED)
                    ->update([
                        'status' => TelegramTask::STATUS_FAILED,
                        'result' => json_encode(['error' => 'Lease expired — no report received within timeout']),
                        'updated_at' => now(),
                    ]);

                // Bulk fail link states (one query per group)
                foreach ($linkStateGroups as $g) {
                    $batchLinkStates += TelegramAccountLinkState::query()
                        ->where('account_phone', $g['phone'])
                        ->where('link_hash', $g['link_hash'])
                        ->where('action', $g['action'])
                        ->where('state', TelegramAccountLinkState::STATE_IN_PROGRESS)
                        ->update(['state' => TelegramAccountLinkState::STATE_FAILED, 'last_error' => 'Lease expired']);
                }

                // Bulk fail memberships — group task_ids by order_id then issue one query
                $byOrder = [];
                foreach ($membershipPairs as $p) {
                    $byOrder[$p['order_id']][] = $p['task_id'];
                }
                foreach ($byOrder as $orderId => $taskIdList) {
                    $batchMemberships += TelegramOrderMembership::query()
                        ->where('order_id', $orderId)
                        ->where('state', TelegramOrderMembership::STATE_IN_PROGRESS)
                        ->whereIn('subscribed_task_id', $taskIdList)
                        ->update(['state' => TelegramOrderMembership::STATE_FAILED, 'last_error' => 'Lease expired']);
                }
            });

            $totalFailed += $batchFailed;
            $totalLinkStates += $batchLinkStates;
            $totalMemberships += $batchMemberships;

            // If we got fewer than BATCH_SIZE, no more pending work
            if ($batch->count() < self::BATCH_SIZE) {
                break;
            }
        }

        // Sweep stuck memberships/link states (single bulk update each — no transaction needed)
        $stuckMemberships = TelegramOrderMembership::query()
            ->where('state', TelegramOrderMembership::STATE_IN_PROGRESS)
            ->where('updated_at', '<', $cutoff)
            ->limit(5000)
            ->update(['state' => TelegramOrderMembership::STATE_FAILED, 'last_error' => 'Stuck in_progress timeout']);

        $stuckLinkStates = TelegramAccountLinkState::query()
            ->where('state', TelegramAccountLinkState::STATE_IN_PROGRESS)
            ->where('updated_at', '<', $cutoff)
            ->limit(5000)
            ->update(['state' => TelegramAccountLinkState::STATE_FAILED, 'last_error' => 'Stuck in_progress timeout']);

    }
}
