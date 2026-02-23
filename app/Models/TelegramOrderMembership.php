<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramOrderMembership extends Model
{
    protected $table = 'telegram_order_memberships';

    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_SUBSCRIBED = 'subscribed';
    public const STATE_UNSUBSCRIBED = 'unsubscribed';
    public const STATE_FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'account_phone',
        'link_hash',
        'link',
        'state',
        'subscribed_task_id',
        'unsubscribed_task_id',
        'subscribed_at',
        'unsubscribed_at',
        'last_error',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
