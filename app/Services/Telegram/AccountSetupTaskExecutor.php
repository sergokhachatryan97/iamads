<?php

namespace App\Services\Telegram;

use App\Models\MtprotoAccountTask;
use App\Models\MtprotoTelegramAccount;
use danog\MadelineProto\API;
use Illuminate\Support\Facades\Log;

/**
 * Service for executing account setup tasks.
 *
 * Each method is idempotent: checks current state first, and if already correct, returns ok.
 */
class AccountSetupTaskExecutor
{
    /**
     * Execute a task by type.
     *
     * @param string $taskType
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function execute(string $taskType, API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        return match ($taskType) {
            MtprotoAccountTask::TASK_ENSURE_2FA => $this->ensure2FA($madeline, $account, $payload),
            MtprotoAccountTask::TASK_UPDATE_NAME => $this->updateName($madeline, $account, $payload),
            MtprotoAccountTask::TASK_UPDATE_UNIQUE_NAME => $this->updateUniqueUsername($madeline, $account, $payload),
            MtprotoAccountTask::TASK_UPDATE_BIO => $this->updateBio($madeline, $account, $payload),
            MtprotoAccountTask::TASK_PRIVACY_PHONE_HIDDEN => $this->setPrivacyPhoneHidden($madeline, $account, $payload),
            MtprotoAccountTask::TASK_PRIVACY_LAST_SEEN_EVERYBODY => $this->setPrivacyLastSeenEverybody($madeline, $account, $payload),
            MtprotoAccountTask::TASK_SET_PHOTO_JPG => $this->setPhotoJpg($madeline, $account, $payload),
            MtprotoAccountTask::TASK_SET_PHOTO_GIF => $this->setPhotoGif($madeline, $account, $payload),
            MtprotoAccountTask::TASK_STORY_IMAGE => $this->postStoryImage($madeline, $account, $payload),
            MtprotoAccountTask::TASK_STORY_VIDEO => $this->postStoryVideo($madeline, $account, $payload),
            default => [
                'ok' => false,
                'error_code' => 'UNKNOWN_TASK_TYPE',
                'error' => "Unknown task type: {$taskType}",
            ],
        };
    }

    /**
     * Ensure 2FA is enabled (check and optionally enable).
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    /**
     * Ensure 2FA is enabled (dispatches job for async processing).
     *
     * This method now dispatches Enable2faJob which handles:
     * - Checking if 2FA is already enabled
     * - Generating unique password
     * - Setting recovery email with Gmail alias
     * - Dispatching confirmation job
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function ensure2FA(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            // Quick check: if 2FA state exists and is confirmed, return immediately
            $state = \App\Models\Mtproto2faState::query()
                ->where('account_id', $account->id)
                ->where('status', \App\Models\Mtproto2faState::STATUS_CONFIRMED)
                ->first();

            if ($state) {
                return ['ok' => true, 'meta' => ['already_enabled' => true]];
            }

            // Check if we should enable 2FA
            $shouldEnable = config('telegram_mtproto.setup.2fa.enable', false);
            if (!$shouldEnable) {
                return ['ok' => true, 'meta' => ['2fa_not_enabled' => true, 'enable_disabled' => true]];
            }

            // Check if base email is configured
            $baseEmail = config('telegram_mtproto.setup.2fa.base_email');
            if (!$baseEmail) {
                return [
                    'ok' => false,
                    'error_code' => '2FA_BASE_EMAIL_NOT_CONFIGURED',
                    'error' => '2FA base_email not configured',
                ];
            }

            // Dispatch Enable2faJob to handle 2FA enablement asynchronously
            \App\Jobs\Enable2faJob::dispatch($account->id)
                ->onQueue('tg-2fa-enable')
                ->afterCommit();

            Log::info('2FA enablement job dispatched', [
                'account_id' => $account->id,
            ]);

            return ['ok' => true, 'meta' => ['job_dispatched' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'ENSURE_2FA_ERROR');
        }
    }

    /**
     * Update profile name.
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function updateName(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            $newName = $payload['name'] ?? null;
            if (!$newName) {
                return [
                    'ok' => false,
                    'error_code' => 'NAME_MISSING',
                    'error' => 'Name not provided in payload',
                ];
            }

            // Get current name
            $full = $madeline->getFullInfo('me');
            $currentFirstName = $full['User']['first_name'] ?? '';
            $currentLastName = $full['User']['last_name'] ?? '';

            // Check if already set (idempotent)
            if ($currentFirstName === $newName && empty($currentLastName)) {
                return ['ok' => true, 'meta' => ['already_set' => true]];
            }

            // Update name
            $madeline->account->updateProfile([
                'first_name' => $newName,
                'last_name' => '',
            ]);

            return ['ok' => true, 'meta' => ['name_updated' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'UPDATE_NAME_ERROR');
        }
    }

    public function updateUniqueUsername(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            $handle = $payload['username'] ?? null;
            if (!$handle) {
                return ['ok' => false, 'error_code' => 'USERNAME_MISSING', 'error' => 'username not provided'];
            }

            $handle = strtolower(trim($handle));

            if (!preg_match('/^[a-z0-9_]{5,32}$/', $handle)) {
                return ['ok' => false, 'error_code' => 'USERNAME_INVALID', 'error' => 'Invalid username format'];
            }

            // Idempotency: check current
            $full = $madeline->getFullInfo('me');
            $current = $full['User']['username'] ?? null;
            if ($current === $handle) {
                return ['ok' => true, 'meta' => ['already_set' => true]];
            }

            // Unique username update (method name may vary by Madeline version)
            $madeline->account->updateUsername(['username' => $handle]);

            return ['ok' => true, 'meta' => ['username_updated' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'UPDATE_USERNAME_ERROR');
        }
    }

    /**
     * Update profile bio.
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function updateBio(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            $newBio = $payload['bio'] ?? null;
            if (!$newBio) {
                return [
                    'ok' => false,
                    'error_code' => 'BIO_MISSING',
                    'error' => 'Bio not provided in payload',
                ];
            }

            // Get current bio
            $full = $madeline->getFullInfo('me');
            $currentBio = $full['User']['about'] ?? '';

            // Check if already set (idempotent)
            if ($currentBio === $newBio) {
                return ['ok' => true, 'meta' => ['already_set' => true]];
            }

            // Update bio
            $madeline->account->updateProfile([
                'about' => $newBio,
            ]);

            return ['ok' => true, 'meta' => ['bio_updated' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'UPDATE_BIO_ERROR');
        }
    }

    /**
     * Set phone visibility to hidden.
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function setPrivacyPhoneHidden(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            // Get current privacy settings
            $privacy = $madeline->account->getPrivacy(['key' => ['_' => 'inputPrivacyKeyPhoneNumber']]);

            // Check if already hidden (idempotent)
            if (isset($privacy['rules']) && count($privacy['rules']) > 0) {
                $firstRule = $privacy['rules'][0];
                if (isset($firstRule['_']) && $firstRule['_'] === 'privacyValueDisallowAll') {
                    return ['ok' => true, 'meta' => ['already_hidden' => true]];
                }
            }

            // Set phone to hidden
            $madeline->account->setPrivacy([
                'key' => ['_' => 'inputPrivacyKeyPhoneNumber'],
                'rules' => [['_' => 'inputPrivacyValueDisallowAll']],
            ]);

            return ['ok' => true, 'meta' => ['phone_hidden' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'PRIVACY_PHONE_ERROR');
        }
    }

    /**
     * Set last seen to Everybody.
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function setPrivacyLastSeenEverybody(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            // Get current privacy settings
            $privacy = $madeline->account->getPrivacy(['key' => ['_' => 'inputPrivacyKeyStatusTimestamp']]);

            // Check if already set to Everybody (idempotent)
            if (isset($privacy['rules']) && count($privacy['rules']) > 0) {
                $firstRule = $privacy['rules'][0];
                if (isset($firstRule['_']) && $firstRule['_'] === 'privacyValueAllowAll') {
                    return ['ok' => true, 'meta' => ['already_everybody' => true]];
                }
            }

            // Set last seen to Everybody
            $madeline->account->setPrivacy([
                'key' => ['_' => 'inputPrivacyKeyStatusTimestamp'],
                'rules' => [['_' => 'inputPrivacyValueAllowAll']],
            ]);

            return ['ok' => true, 'meta' => ['last_seen_everybody' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'PRIVACY_LAST_SEEN_ERROR');
        }
    }

    /**
     * Set profile photo from JPG file.
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function setPhotoJpg(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            // Support both 'file_path' (absolute) and 'local_path' (storage relative)
            $filePath = $payload['file_path'] ?? null;
            if (!$filePath && isset($payload['local_path'])) {
                $filePath = \Illuminate\Support\Facades\Storage::path($payload['local_path']);
            }

            if (!$filePath || !file_exists($filePath)) {
                return [
                    'ok' => false,
                    'error_code' => 'FILE_NOT_FOUND',
                    'error' => "JPG file not found: {$filePath}",
                ];
            }

            // Upload and set photo
            $madeline->photos->updateProfilePhoto([
                'file' => $filePath,
            ]);

            return ['ok' => true, 'meta' => ['photo_set' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'SET_PHOTO_JPG_ERROR');
        }
    }

    /**
     * Set profile photo from GIF file.
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function setPhotoGif(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            // Support both 'file_path' (absolute) and 'local_path' (storage relative)
            $filePath = $payload['file_path'] ?? null;
            if (!$filePath && isset($payload['local_path'])) {
                $filePath = \Illuminate\Support\Facades\Storage::path($payload['local_path']);
            }

            if (!$filePath || !file_exists($filePath)) {
                return [
                    'ok' => false,
                    'error_code' => 'FILE_NOT_FOUND',
                    'error' => "GIF file not found: {$filePath}",
                ];
            }

            // Upload and set photo (GIF as animated profile photo)
            $madeline->photos->uploadProfilePhoto([
                'file' => $filePath,
            ]);

            return ['ok' => true, 'meta' => ['gif_photo_set' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'SET_PHOTO_GIF_ERROR');
        }
    }

    /**
     * Post story from image.
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function postStoryImage(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            // Support both 'file_path' (absolute) and 'local_path' (storage relative)
            $filePath = $payload['file_path'] ?? null;
            if (!$filePath && isset($payload['local_path'])) {
                $filePath = \Illuminate\Support\Facades\Storage::path($payload['local_path']);
            }

            if (!$filePath || !file_exists($filePath)) {
                return [
                    'ok' => false,
                    'error_code' => 'FILE_NOT_FOUND',
                    'error' => "Story image file not found: {$filePath}",
                ];
            }

            // Upload and post story
            $madeline->stories->sendStory([
                'peer' => ['_' => 'inputPeerSelf'],
                'media' => [
                    '_' => 'inputMediaUploadedPhoto',
                    'file' => $filePath,
                ],
                'privacy_rules' => [
                    ['_' => 'inputPrivacyValueAllowAll'],
                ],
            ]);

            return ['ok' => true, 'meta' => ['story_posted' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'STORY_IMAGE_ERROR');
        }
    }

    /**
     * Post story from video.
     *
     * @param API $madeline
     * @param MtprotoTelegramAccount $account
     * @param array|null $payload
     * @return array{ok: bool, error_code?: string, error?: string, meta?: array}
     */
    public function postStoryVideo(API $madeline, MtprotoTelegramAccount $account, ?array $payload): array
    {
        try {
            // Support both 'file_path' (absolute) and 'local_path' (storage relative)
            $filePath = $payload['file_path'] ?? null;
            if (!$filePath && isset($payload['local_path'])) {
                $filePath = \Illuminate\Support\Facades\Storage::path($payload['local_path']);
            }

            if (!$filePath || !file_exists($filePath)) {
                return [
                    'ok' => false,
                    'error_code' => 'FILE_NOT_FOUND',
                    'error' => "Story video file not found: {$filePath}",
                ];
            }

            // Upload and post story video
            $madeline->stories->sendStory([
                'peer' => ['_' => 'inputPeerSelf'],
                'media' => [
                    '_' => 'inputMediaUploadedDocument',
                    'file' => $filePath,
                    'mime_type' => 'video/mp4',
                ],
                'privacy_rules' => [
                    ['_' => 'inputPrivacyValueAllowAll'], // Everybody
                ],
            ]);

            return ['ok' => true, 'meta' => ['story_video_posted' => true]];

        } catch (\Throwable $e) {
            return $this->wrapError($e, 'STORY_VIDEO_ERROR');
        }
    }

    /**
     * Wrap exception into standardized error array.
     */
    private function wrapError(\Throwable $e, string $defaultErrorCode): array
    {
        $code = strtoupper((string) ($e->getCode() ?? ''));
        $msg = strtoupper((string) $e->getMessage());

        // Try to extract RPC error code if it's an RPCErrorException
        if ($e instanceof \danog\MadelineProto\RPCErrorException) {
            $rpc = strtoupper((string) ($e->rpc ?? ''));
            $errorCode = $rpc !== '' ? $rpc : $defaultErrorCode;
        } else {
            $errorCode = $defaultErrorCode;
        }

        return [
            'ok' => false,
            'error_code' => $errorCode,
            'error' => $e->getMessage(),
        ];
    }
}
