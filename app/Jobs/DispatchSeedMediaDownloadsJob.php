<?php

namespace App\Jobs;

use App\Models\AccountProfileSeed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatcher job that finds seeds needing media downloads and dispatches download jobs.
 *
 * Routes to tg-media-prep queue with low concurrency.
 */
class DispatchSeedMediaDownloadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function handle(): void
    {
        // Find seeds that need downloads
        $seeds = AccountProfileSeed::query()
            ->where(function ($query) {
                $query->where('status', AccountProfileSeed::STATUS_NEEDS_DOWNLOAD)
                    ->orWhere(function ($q) {
                        $q->whereNotNull('profile_photo_url')
                            ->whereNull('profile_photo_local_path');
                    })
                    ->orWhere(function ($q) {
                        $q->whereNotNull('story_url')
                            ->whereNull('story_local_path');
                    });
            })
            ->limit(100) // Process in batches
            ->get();

        $dispatched = 0;

        foreach ($seeds as $seed) {
            try {
                // Dispatch download for profile photo if needed
                if ($seed->profile_photo_url && !$seed->profile_photo_local_path) {
                    DownloadSeedMediaJob::dispatch($seed->id, 'profile_photo')
                        ->onQueue('tg-media-prep')
                        ->afterCommit();
                    $dispatched++;
                }

                // Dispatch download for story if needed
                if ($seed->story_url && !$seed->story_local_path) {
                    DownloadSeedMediaJob::dispatch($seed->id, 'story')
                        ->onQueue('tg-media-prep')
                        ->afterCommit();
                    $dispatched++;
                }

            } catch (\Throwable $e) {
                Log::error('Failed to dispatch media download', [
                    'seed_id' => $seed->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SEED_MEDIA_DISPATCH_OK', [
            'seeds_processed' => $seeds->count(),
            'jobs_dispatched' => $dispatched,
        ]);
    }
}
