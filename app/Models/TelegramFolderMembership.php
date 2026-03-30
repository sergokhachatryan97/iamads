<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramFolderMembership extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REMOVED = 'removed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'mtproto_telegram_account_id',
        'target_link',
        'target_link_hash',
        'peer_type',
        'target_username',
        'target_peer_id',
        'folder_id',
        'folder_title',
        'folder_share_link',
        'folder_share_slug',
        'added_at',
        'remove_at',
        'status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'folder_id' => 'integer',
            'added_at' => 'datetime',
            'remove_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function mtprotoTelegramAccount(): BelongsTo
    {
        return $this->belongsTo(MtprotoTelegramAccount::class, 'mtproto_telegram_account_id');
    }
}
