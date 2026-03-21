<?php

namespace App\Jobs;

use App\Models\YouTubeAccountTargetState;
use App\Models\YouTubeTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resets expired YouTube tasks (LEASED with leased_until in the past) back to PENDING
 * so they can be claimed again. Runs every minute via Laravel scheduler.
 */
class CleanExpiredYouTubeTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function handle(): void
    {
        $expiredTasks = YouTubeTask::query()
            ->where('status', YouTubeTask::STATUS_LEASED)
            ->whereNotNull('leased_until')
            ->where('leased_until', '<', now())
            ->get();

        $deletedCount = 0;
        $stateCleaned = 0;

        foreach ($expiredTasks as $task) {
            DB::transaction(function () use ($task, &$deletedCount, &$stateCleaned) {

                if (
                    $task->action === 'subscribe' &&
                    $task->target_hash
                ) {
                    $affected = YouTubeAccountTargetState::query()
                        ->where('account_identity', $task->account_identity)
                        ->where('action', $task->action)
                        ->where('target_hash', $task->target_hash)
                        ->where('state', YouTubeAccountTargetState::STATE_IN_PROGRESS)
                        ->where('last_task_id', $task->id) // safer cleanup
                        ->delete();

                    if ($affected > 0) {
                        $stateCleaned += $affected;
                    }
                }

                $task->delete();
                $deletedCount++;
            });
        }

        Log::info('CleanExpiredYouTubeTasksJob', [
            'deleted_tasks' => $deletedCount,
            'cleaned_states' => $stateCleaned,
        ]);
    }
}
