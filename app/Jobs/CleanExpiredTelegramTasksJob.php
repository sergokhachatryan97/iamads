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
 * Fails Telegram tasks stuck in LEASED or PENDING status past their lease expiry (5+ minutes).
 * Cleans up related TelegramAccountLinkState and TelegramOrderMembership records.
 */
class CleanExpiredTelegramTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    private const GRACE_MINUTES = 5;

    public function handle(): void
    {
        $cutoff = now()->subMinutes(self::GRACE_MINUTES);

        $expiredTasks = TelegramTask::query()
            ->whereIn('status', [TelegramTask::STATUS_LEASED])
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($q2) use ($cutoff) {
                    $q2->whereNotNull('leased_until')
                        ->where('leased_until', '<', $cutoff);
                })->orWhere(function ($q2) use ($cutoff) {
                    // Catch tasks without leased_until that are stuck
                    $q2->whereNull('leased_until')
                        ->where('updated_at', '<', $cutoff);
                });
            })
            ->get();

        $failedCount = 0;
        $linkStateCleaned = 0;
        $membershipCleaned = 0;

        foreach ($expiredTasks as $task) {
            DB::transaction(function () use ($task, &$failedCount, &$linkStateCleaned, &$membershipCleaned) {
                $task->update([
                    'status' => TelegramTask::STATUS_FAILED,
                    'result' => ['error' => 'Lease expired — no report received within timeout'],
                ]);
                $failedCount++;

                // Clean up TelegramAccountLinkState if it's still in_progress for this task
                if ($task->link_hash) {
                    $phone = $task->payload['account_phone'] ?? $task->telegram_account_id;

                    if ($phone) {
                        $affected = TelegramAccountLinkState::query()
                            ->where('account_phone', $phone)
                            ->where('link_hash', $task->link_hash)
                            ->where('state', TelegramAccountLinkState::STATE_IN_PROGRESS)
                            ->where('last_task_id', $task->id)
                            ->update(['state' => TelegramAccountLinkState::STATE_FAILED, 'last_error' => 'Lease expired']);

                        $linkStateCleaned += $affected;
                    }
                }

                // Clean up TelegramOrderMembership if it's still in_progress for this task
                if ($task->order_id) {
                    $affected = TelegramOrderMembership::query()
                        ->where('order_id', $task->order_id)
                        ->where('state', TelegramOrderMembership::STATE_IN_PROGRESS)
                        ->where('subscribed_task_id', $task->id)
                        ->update(['state' => TelegramOrderMembership::STATE_FAILED, 'last_error' => 'Lease expired']);

                    $membershipCleaned += $affected;
                }
            });
        }

        // Fail memberships stuck in_progress for more than 5 minutes
        $stuckMemberships = TelegramOrderMembership::query()
            ->where('state', TelegramOrderMembership::STATE_IN_PROGRESS)
            ->where('updated_at', '<', $cutoff)
            ->update(['state' => TelegramOrderMembership::STATE_FAILED, 'last_error' => 'Stuck in_progress timeout']);

        // Fail link states stuck in_progress for more than 5 minutes
        $stuckLinkStates = TelegramAccountLinkState::query()
            ->where('state', TelegramAccountLinkState::STATE_IN_PROGRESS)
            ->where('updated_at', '<', $cutoff)
            ->update(['state' => TelegramAccountLinkState::STATE_FAILED, 'last_error' => 'Stuck in_progress timeout']);

        Log::info('CleanExpiredTelegramTasksJob', [
            'failed_tasks' => $failedCount,
            'cleaned_link_states' => $linkStateCleaned,
            'cleaned_memberships' => $membershipCleaned,
            'stuck_memberships' => $stuckMemberships,
            'stuck_link_states' => $stuckLinkStates,
        ]);
    }
}
