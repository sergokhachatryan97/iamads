<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MtprotoAccountTask extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRY = 'retry';

    // Task type constants
    public const TASK_ENSURE_2FA = 'ENSURE_2FA';
    public const TASK_UPDATE_NAME = 'UPDATE_NAME';
    public const TASK_UPDATE_UNIQUE_NAME = 'UPDATE_UNIQUE_NAME';
    public const TASK_UPDATE_BIO = 'UPDATE_BIO';
    public const TASK_PRIVACY_PHONE_HIDDEN = 'PRIVACY_PHONE_HIDDEN';
    public const TASK_PRIVACY_LAST_SEEN_EVERYBODY = 'PRIVACY_LAST_SEEN_EVERYBODY';
    public const TASK_SET_PHOTO_JPG = 'SET_PHOTO_JPG';
    public const TASK_SET_PHOTO_GIF = 'SET_PHOTO_GIF';
    public const TASK_STORY_IMAGE = 'STORY_IMAGE';
    public const TASK_STORY_VIDEO = 'STORY_VIDEO';

    /**
     * All required task types for account setup.
     */
    public static function getRequiredTaskTypes(): array
    {
        return [
            self::TASK_ENSURE_2FA,
            self::TASK_UPDATE_NAME,
            self::TASK_UPDATE_UNIQUE_NAME,
            self::TASK_PRIVACY_PHONE_HIDDEN,
            self::TASK_PRIVACY_LAST_SEEN_EVERYBODY,
            self::TASK_SET_PHOTO_JPG,
            self::TASK_SET_PHOTO_GIF,
            self::TASK_STORY_IMAGE,
            self::TASK_STORY_VIDEO,
        ];
    }

    /**
     * Task types that require media upload (use slow queue).
     */
    public static function getMediaTaskTypes(): array
    {
        return [
            self::TASK_SET_PHOTO_JPG,
            self::TASK_SET_PHOTO_GIF,
            self::TASK_STORY_IMAGE,
            self::TASK_STORY_VIDEO,
        ];
    }

    protected $fillable = [
        'account_id',
        'task_type',
        'payload_json',
        'status',
        'attempts',
        'retry_at',
        'last_error_code',
        'last_error',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'retry_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Get the account this task belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MtprotoTelegramAccount::class, 'account_id');
    }

    /**
     * Check if task is final (done or permanently failed).
     */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    /**
     * Check if task is eligible to run (pending or retry with retry_at <= now).
     */
    public function isEligibleToRun(): bool
    {
        if ($this->isFinal()) {
            return false;
        }

        if ($this->status === self::STATUS_RUNNING) {
            return false;
        }

        if ($this->status === self::STATUS_RETRY && $this->retry_at && $this->retry_at->isFuture()) {
            return false;
        }

        return true;
    }
}
