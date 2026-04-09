<?php

namespace App\Jobs;

use App\Models\MaxTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanExpiredMaxTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    private const GRACE_MINUTES = 5;

    public function handle(): void
    {
        $cutoff = now()->subMinutes(self::GRACE_MINUTES);

        $failedCount = MaxTask::query()
            ->where('status', MaxTask::STATUS_LEASED)
            ->whereNotNull('leased_until')
            ->where('leased_until', '<', $cutoff)
            ->update([
                'status' => MaxTask::STATUS_FAILED,
                'result' => json_encode(['error' => 'Lease expired — no report received within timeout']),
            ]);

        Log::info('CleanExpiredMaxTasksJob', [
            'failed_tasks' => $failedCount,
        ]);
    }
}
