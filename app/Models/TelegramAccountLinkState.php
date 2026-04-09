<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fast global dedup table per (account_phone, link_hash, action).
 *
 * Blocking states (in_progress, subscribed) prevent duplicate claims.
 * Non-blocking states (failed, unsubscribed, expired) are kept for statistics.
 */
class TelegramAccountLinkState extends Model
{
    protected $table = 'telegram_account_link_states';

    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_SUBSCRIBED = 'subscribed';
    public const STATE_UNSUBSCRIBED = 'unsubscribed';
    public const STATE_FAILED = 'failed';
    public const STATE_EXPIRED = 'expired';

    public const BLOCKING_STATES = [self::STATE_IN_PROGRESS, self::STATE_SUBSCRIBED];

    protected $fillable = [
        'account_phone',
        'link_hash',
        'action',
        'state',
        'last_task_id',
        'last_error',
    ];

    public function isBlocking(): bool
    {
        return in_array($this->state, self::BLOCKING_STATES, true);
    }

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
