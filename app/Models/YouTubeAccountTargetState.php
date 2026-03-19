<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Global state per (account_identity, action, target_hash) for YouTube stateful actions (subscribe).
 */
class YouTubeAccountTargetState extends Model
{
    protected $table = 'youtube_account_target_states';

    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_SUBSCRIBED = 'subscribed';
    public const STATE_FAILED = 'failed';
    public const STATE_IGNORED = 'ignored';

    protected $fillable = [
        'account_identity',
        'action',
        'target_type',
        'normalized_target',
        'target_hash',
        'state',
        'last_task_id',
        'last_error',
    ];
}
