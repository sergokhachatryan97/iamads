<?php

namespace App\Jobs;

use App\Models\AccountProfileSeed;
use App\Models\MtprotoAccountTask;
use App\Models\MtprotoTelegramAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Dispatcher job that creates setup tasks for eligible accounts.
 *
 * Selects accounts where is_active=1, disabled_at is null, is_probe=0,
 * and ensures required tasks exist (upsert) with status pending if not done.
 */
class DispatchAccountSetupTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function handle(): void
    {
        // Check if setup is enabled
        if (!config('telegram_mtproto.setup.enabled', false)) {
            Log::debug('Account setup disabled, skipping dispatch');
            return;
        }

        // Select eligible accounts
        $accounts = MtprotoTelegramAccount::query()
            ->where('is_active', true)
            ->whereNull('disabled_at')
            ->where('is_probe', false)
            ->limit(100) // Process in batches
            ->get();

        $created = 0;
        $dispatched = 0;

        foreach ($accounts as $account) {
            try {
                // Find matching profile seed (if enabled)
                $seed = $this->findMatchingSeed($account);

                // Only proceed if seed is ready (or no seed matching enabled)
                if ($seed && !$seed->isReady()) {
                    Log::debug('Skipping account - seed not ready', [
                        'account_id' => $account->id,
                        'seed_status' => $seed->status,
                    ]);
                    continue;
                }

                // Build task list based on seed data
                $tasksToCreate = $this->buildTaskList($seed);

                foreach ($tasksToCreate as $taskType) {
                    // Check if we should create this task based on seed data
                    if (!$this->shouldCreateTask($taskType, $seed)) {
                        continue;
                    }

                    // Upsert task (unique constraint prevents duplicates)
                    // Only create/update if task doesn't exist or is not done
                    $existingTask = MtprotoAccountTask::query()
                        ->where('account_id', $account->id)
                        ->where('task_type', $taskType)
                        ->first();

                    if ($existingTask && $existingTask->status === MtprotoAccountTask::STATUS_DONE) {
                        // Task already done, skip
                        continue;
                    }

                    // Get payload from seed or default
                    $payload = $this->getPayloadForTask($taskType, $seed, $account);

                    $task = MtprotoAccountTask::updateOrCreate(
                        [
                            'account_id' => $account->id,
                            'task_type' => $taskType,
                        ],
                        [
                            'status' => $existingTask?->status ?? MtprotoAccountTask::STATUS_PENDING,
                            'payload_json' => $existingTask?->payload_json ?? $payload,
                            'attempts' => $existingTask?->attempts ?? 0,
                            'retry_at' => $existingTask?->retry_at,
                            'last_error_code' => $existingTask?->last_error_code,
                            'last_error' => $existingTask?->last_error,
                        ]
                    );

                    // Only count as created if it was newly created (not updated)
                    if ($task->wasRecentlyCreated) {
                        $created++;
                    }

                    // Dispatch runner job if task is eligible
                    if ($task->isEligibleToRun()) {
                        $queue = $this->getQueueForTaskType($taskType);

                        RunAccountSetupTaskJob::dispatch($task->id)
                            ->onQueue($queue)
                            ->afterCommit();

                        $dispatched++;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch setup tasks for account', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Account setup tasks dispatched', [
            'accounts_processed' => $accounts->count(),
            'tasks_created' => $created,
            'tasks_dispatched' => $dispatched,
        ]);
    }

    /**
     * Build list of tasks to create based on seed data.
     */
    private function buildTaskList(?AccountProfileSeed $seed): array
    {
        $baseTasks = [
            MtprotoAccountTask::TASK_ENSURE_2FA,
            MtprotoAccountTask::TASK_PRIVACY_PHONE_HIDDEN,
            MtprotoAccountTask::TASK_PRIVACY_LAST_SEEN_EVERYBODY,
        ];

        // Add seed-specific tasks
        if ($seed) {
            if ($seed->display_name) {
                $baseTasks[] = MtprotoAccountTask::TASK_UPDATE_NAME;
            }
            if ($seed->username) {
                $baseTasks[] = MtprotoAccountTask::TASK_UPDATE_UNIQUE_NAME;
            }
            if ($seed->bio) {
                $baseTasks[] = MtprotoAccountTask::TASK_UPDATE_BIO;
            }
            if ($seed->profile_photo_local_path) {
                // Determine JPG vs GIF based on MIME
                $baseTasks[] = $this->getMediaTaskType($seed->profile_photo_mime, MtprotoAccountTask::TASK_SET_PHOTO_JPG);
            }
            if ($seed->story_local_path) {
                // Determine IMAGE vs VIDEO based on MIME
                $baseTasks[] = $this->getMediaTaskType($seed->story_mime, MtprotoAccountTask::TASK_STORY_IMAGE);
            }
        } else {
            // No seed: use default required tasks
            $baseTasks = array_merge($baseTasks, [
                MtprotoAccountTask::TASK_UPDATE_NAME,
                MtprotoAccountTask::TASK_UPDATE_UNIQUE_NAME,
                MtprotoAccountTask::TASK_SET_PHOTO_JPG,
                MtprotoAccountTask::TASK_SET_PHOTO_GIF,
                MtprotoAccountTask::TASK_STORY_IMAGE,
                MtprotoAccountTask::TASK_STORY_VIDEO,
            ]);
        }

        return $baseTasks;
    }

    /**
     * Find matching profile seed for account.
     */
    private function findMatchingSeed(MtprotoTelegramAccount $account): ?AccountProfileSeed
    {
        $matchByUsername = config('telegram_mtproto.sheet.match_by_username', false);

        if (!$matchByUsername) {
            // Option 2: Pick random seed (or first available)
            return AccountProfileSeed::query()
                ->where('status', AccountProfileSeed::STATUS_READY)
                ->inRandomOrder()
                ->first();
        }

        // Option 1: Match by username
        // Check if account has username field (may need to adjust based on actual schema)
        $username = $account->username ?? null;
        if (!$username) {
            return null;
        }

        $normalized = AccountProfileSeed::normalizeUsername($username);
        return AccountProfileSeed::query()
            ->where('username', $normalized)
            ->where('status', AccountProfileSeed::STATUS_READY)
            ->first();
    }

    /**
     * Check if task should be created based on seed data.
     */
    private function shouldCreateTask(string $taskType, ?AccountProfileSeed $seed): bool
    {
        // If no seed, use default behavior (create all tasks)
        if (!$seed) {
            return true;
        }

        // Check if seed has required data for this task
        return match ($taskType) {
            MtprotoAccountTask::TASK_UPDATE_NAME => !empty($seed->display_name),
            MtprotoAccountTask::TASK_UPDATE_UNIQUE_NAME => !empty($seed->username),
            MtprotoAccountTask::TASK_UPDATE_BIO => !empty($seed->bio),
            MtprotoAccountTask::TASK_SET_PHOTO_JPG,
            MtprotoAccountTask::TASK_SET_PHOTO_GIF => !empty($seed->profile_photo_local_path),
            MtprotoAccountTask::TASK_STORY_IMAGE,
            MtprotoAccountTask::TASK_STORY_VIDEO => !empty($seed->story_local_path),
            default => true, // Other tasks always created
        };
    }

    /**
     * Get payload for task from seed or default.
     */
    private function getPayloadForTask(string $taskType, ?AccountProfileSeed $seed, MtprotoTelegramAccount $account): ?array
    {
        // If seed exists and has data, use it
        if ($seed) {
            return match ($taskType) {
                MtprotoAccountTask::TASK_UPDATE_NAME => $seed->display_name ? ['name' => $seed->display_name] : null,
                MtprotoAccountTask::TASK_UPDATE_UNIQUE_NAME => $seed->username ? ['username' => $seed->username] : null,
                MtprotoAccountTask::TASK_UPDATE_BIO => $seed->bio ? ['bio' => $seed->bio] : null,
                MtprotoAccountTask::TASK_SET_PHOTO_JPG,
                MtprotoAccountTask::TASK_SET_PHOTO_GIF => $this->getMediaPayload($seed->profile_photo_local_path, $seed->profile_photo_mime),
                MtprotoAccountTask::TASK_STORY_IMAGE,
                MtprotoAccountTask::TASK_STORY_VIDEO => $this->getMediaPayload($seed->story_local_path, $seed->story_mime),
                default => null,
            };
        }

        // Fallback to default payload
        return $this->getDefaultPayload($taskType);
    }

    /**
     * Get media payload with full storage path.
     */
    private function getMediaPayload(?string $localPath, ?string $mime): ?array
    {
        if (!$localPath || !Storage::exists($localPath)) {
            return null;
        }

        // Convert storage path to absolute path
        $absolutePath = Storage::path($localPath);

        return [
            'file_path' => $absolutePath,
            'local_path' => $localPath, // Keep relative path too
            'mime' => $mime,
        ];
    }

    /**
     * Get default payload for task type.
     */
    private function getDefaultPayload(string $taskType): ?array
    {
        // Most tasks don't need payload, but some do (e.g., file paths for media)
        $config = config('telegram_mtproto.setup', []);

        return match ($taskType) {
            MtprotoAccountTask::TASK_SET_PHOTO_JPG => [
                'file_path' => $config['media']['default_jpg_path'] ?? null,
            ],
            MtprotoAccountTask::TASK_SET_PHOTO_GIF => [
                'file_path' => $config['media']['default_gif_path'] ?? null,
            ],
            MtprotoAccountTask::TASK_STORY_IMAGE => [
                'file_path' => $config['media']['story_image_path'] ?? null,
            ],
            MtprotoAccountTask::TASK_STORY_VIDEO => [
                'file_path' => $config['media']['story_video_path'] ?? null,
            ],
            MtprotoAccountTask::TASK_UPDATE_NAME => [
                'name' => null, // Will be set from config or account data
            ],
            MtprotoAccountTask::TASK_UPDATE_UNIQUE_NAME => [
                'username' => null,
            ],
            default => null,
        };
    }

    /**
     * Get queue name for task type.
     */
    private function getQueueForTaskType(string $taskType): string
    {
        $mediaTypes = MtprotoAccountTask::getMediaTaskTypes();

        if (in_array($taskType, $mediaTypes, true)) {
            return 'tg-setup-media';
        }

        return 'tg-setup-fast';
    }

    /**
     * Determine task type for media based on MIME.
     */
    private function getMediaTaskType(?string $mime, string $defaultType): string
    {
        if (!$mime) {
            return $defaultType;
        }

        // Determine if GIF or video
        if (str_contains($mime, 'gif')) {
            return str_contains($defaultType, 'PHOTO') ? MtprotoAccountTask::TASK_SET_PHOTO_GIF : $defaultType;
        }

        if (str_contains($mime, 'video')) {
            return str_contains($defaultType, 'STORY') ? MtprotoAccountTask::TASK_STORY_VIDEO : $defaultType;
        }

        // Default to JPG for photos, IMAGE for stories
        if (str_contains($defaultType, 'PHOTO')) {
            return MtprotoAccountTask::TASK_SET_PHOTO_JPG;
        }

        return MtprotoAccountTask::TASK_STORY_IMAGE;
    }
}
