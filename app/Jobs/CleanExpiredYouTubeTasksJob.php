<?php

namespace App\Jobs;

use App\Models\YouTubeTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        $count = YouTubeTask::query()
            ->where('status', YouTubeTask::STATUS_LEASED)
            ->where('leased_until', '<', now())
            ->update([
                'status' => YouTubeTask::STATUS_PENDING,
                'leased_until' => null,
            ]);

        Log::info('CleanExpiredYouTubeTasksJob', [
            'reset_count' => $count,
        ]);
    }
}
