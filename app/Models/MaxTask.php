<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MaxTask extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'max_tasks';

    protected $fillable = [
        'id',
        'order_id',
        'account_identity',
        'action',
        'link',
        'link_hash',
        'target_hash',
        'status',
        'leased_until',
        'payload',
        'result',
    ];

    protected $casts = [
        'leased_until' => 'datetime',
        'payload' => 'array',
        'result' => 'array',
    ];

    public const STATUS_LEASED = 'leased';
    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (MaxTask $task) {
            if (empty($task->id)) {
                $task->id = (string) Str::ulid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
