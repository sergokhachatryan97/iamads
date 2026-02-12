<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class TelegramTask extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_id',
        'subject_type',
        'subject_id',
        'action',
        'link_hash',
        'telegram_account_id',
        'provider_account_id',
        'status',
        'leased_until',
        'attempt',
        'payload',
        'result',
    ];

    protected $casts = [
        'leased_until' => 'datetime',
        'payload' => 'array',
        'result' => 'array',
        'attempt' => 'integer',
    ];

    // Status constants
    public const STATUS_QUEUED = 'queued';
    public const STATUS_LEASED = 'leased';
    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TelegramTask $task) {
            if (empty($task->id)) {
                $task->id = (string) Str::ulid();
            }
        });
    }

    /**
     * Get the order this task belongs to (for backward compatibility).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the subject (Order or ClientServiceQuota) this task belongs to.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the telegram account this task is assigned to.
     */
    public function telegramAccount(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class);
    }

    /**
     * Check if task is eligible for leasing (not already leased or expired).
     */
    public function isEligibleForLease(): bool
    {
        if ($this->status === self::STATUS_DONE || $this->status === self::STATUS_FAILED) {
            return false;
        }

        if ($this->status === self::STATUS_LEASED && $this->leased_until && $this->leased_until->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Check if task is finalized (done or failed).
     */
    public function isFinalized(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
