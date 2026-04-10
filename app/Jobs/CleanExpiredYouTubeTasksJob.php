<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\YouTubeTask;
use App\Support\Performer\OrderDripfeedClaimHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fails YouTube tasks that have been LEASED for 30+ minutes without a report,
 * rolling back dripfeed counters so the order can resume making progress and
 * a new task can be claimed for the same (account, link, action).
 * Runs every minute via Laravel scheduler.
 */
class CleanExpiredYouTubeTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    private const TIMEOUT_MINUTES = 30;

    private const ERROR_MESSAGE = 'Lease expired — no report received within 30 minutes';

    public function handle(): void
    {
        // Use created_at as the source of truth (not leased_until) so this also covers
        // legacy rows claimed under the old 1-hour lease TTL — they fail at 30 min from claim.
        $cutoff = now()->subMinutes(self::TIMEOUT_MINUTES);

        // Pull only IDs — the row state we read here can race with concurrent reports,
        // so we re-fetch and re-check inside each per-task transaction below.
        $expiredIds = YouTubeTask::query()
            ->where('status', YouTubeTask::STATUS_LEASED)
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        $failedCount = 0;
        $skippedCount = 0;

        foreach ($expiredIds as $taskId) {
            DB::transaction(function () use ($taskId, $cutoff, &$failedCount, &$skippedCount) {
                // Re-fetch the task under lock. If a performer reported it between the
                // SELECT above and now, status will no longer be LEASED — we must NOT
                // overwrite a DONE/FAILED row or roll back its dripfeed counter.
                $task = YouTubeTask::query()
                    ->where('id', $taskId)
                    ->lockForUpdate()
                    ->first();

                if ($task === null) {
                    $skippedCount++;
                    return;
                }

                if ($task->status !== YouTubeTask::STATUS_LEASED) {
                    // Already finalized by a concurrent report — leave it alone.
                    $skippedCount++;
                    return;
                }

                if ($task->created_at === null || $task->created_at->gte($cutoff)) {
                    // No longer expired (e.g. clock skew or row updated). Skip.
                    $skippedCount++;
                    return;
                }

                $order = $task->order_id
                    ? Order::query()->where('id', $task->order_id)->lockForUpdate()->first()
                    : null;

                if ($order !== null && $order->status !== Order::STATUS_COMPLETED) {
                    OrderDripfeedClaimHelper::rollbackClaimedUnit($order);
                    $order->update([
                        'provider_last_error' => self::ERROR_MESSAGE,
                        'provider_last_error_at' => now(),
                    ]);
                }

                $task->update([
                    'status' => YouTubeTask::STATUS_FAILED,
                    'result' => ['error' => self::ERROR_MESSAGE],
                ]);

                $failedCount++;
            });
        }

        Log::info('CleanExpiredYouTubeTasksJob', [
            'failed_tasks' => $failedCount,
            'skipped' => $skippedCount,
        ]);
    }
}
