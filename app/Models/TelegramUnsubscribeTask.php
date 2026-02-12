<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TelegramUnsubscribeTask extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'telegram_account_id',
        'link_hash',
        'due_at',
        'status',
        'provider_task_id',
        'telegram_task_id',
        'error',
        'subject_type',
        'subject_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    /**
     * Get the Telegram account.
     */
    public function telegramAccount(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }

    /**
     * Get the subject (Order) that this unsubscribe task belongs to.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the TelegramTask that was created from this unsubscribe task.
     */
    public function telegramTask(): BelongsTo
    {
        return $this->belongsTo(TelegramTask::class, 'telegram_task_id');
    }
}
