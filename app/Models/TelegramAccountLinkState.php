<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Global state per (account_phone, link_hash) for claim flow.
 * Prevents assigning a new subscribe task for the same phone+link while in_progress or subscribed.
 */
class TelegramAccountLinkState extends Model
{
    protected $table = 'telegram_account_link_states';

    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_SUBSCRIBED = 'subscribed';
    public const STATE_UNSUBSCRIBED = 'unsubscribed';
    public const STATE_FAILED = 'failed';

    protected $fillable = [
        'account_phone',
        'link_hash',
        'state',
        'last_task_id',
        'last_error',
    ];

    /**
     * Canonical phone for storage and lookups (consistent with claim/report).
     */
    public static function normalizePhone(string $phone): string
    {
        return trim($phone);
    }

    /**
     * Canonical link hash for storage and lookups (consistent with claim/report).
     */
    public static function linkHash(?string $link): string
    {
        if ($link === null || $link === '') {
            return hash('sha256', '');
        }
        return hash('sha256', strtolower(trim($link)));
    }
}
