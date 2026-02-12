<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramStepEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'telegram_account_id',
        'action',
        'link_hash',
        'ok',
        'error',
        'per_call',
        'retry_after',
        'performed_at',
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'per_call' => 'integer',
            'retry_after' => 'integer',
            'performed_at' => 'datetime',
            'extra' => 'array',
        ];
    }
}
