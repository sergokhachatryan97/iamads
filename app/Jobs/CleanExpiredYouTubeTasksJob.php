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
 * Fails YouTube tasks stuck in LEASED past their lease expiry (plus a 5-minute grace),
 * rolling back dripfeed counters so the order can resume making progress.
 * Runs every minute via Laravel scheduler.
 */
class CleanExpiredYouTubeTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    private const GRACE_MINUTES = 5;

    private const ERROR_MESSAGE = 'Lease expired — no report received within timeout';

    public function handle(): void
    {
        $cutoff = now()->subMinutes(self::GRACE_MINUTES);

        $expiredTasks = YouTubeTask::query()
            ->where('status', YouTubeTask::STATUS_LEASED)
            ->whereNotNull('leased_until')
            ->where('leased_until', '<', $cutoff)
            ->get();

        $failedCount = 0;

        foreach ($expiredTasks as $task) {
            DB::transaction(function () use ($task, &$failedCount) {
                $order = $task->order_id
                    ? Order::query()->lockForUpdate()->find($task->order_id)
                    : null;

                if ($order !== null) {
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
        ]);
    }
}
