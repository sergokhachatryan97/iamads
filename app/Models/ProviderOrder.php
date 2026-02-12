<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProviderOrder extends Model
{
    /** Completed = local status ok (synced from provider completed). */
    public const STATUS_OK = 'ok';

    protected $fillable = [
        'provider_code',
        'remote_order_id',
        'remote_service_id',
        'status',
        'remote_status',
        'quantity',
        'charge',
        'link',
        'start_count',
        'remains',
        'currency',
        'user_remote_id',
        'user_login',
        'fetched_at',
        'provider_sending_at',
        'provider_payload',
        'provider_response',
        'provider_last_error',
        'provider_last_error_at',
    ];

    protected $casts = [
        'charge' => 'decimal:4',
        'start_count' => 'integer',
        'remains' => 'integer',
        'quantity' => 'integer',
        'raw' => 'array',
        'fetched_at' => 'datetime',
        'provider_payload' => 'array',
        'provider_response' => 'array',
        'provider_last_error_at' => 'datetime',
    ];

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->where('remote_status', '=', 'completed');
    }

    /** Exclude failed orders (status fail/failed) for "all without failed" stats. */
    public function scopeWithoutFailed(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['fail', 'failed']);
    }

    /** Only orders with partial status (remote_status or status = partial). */
    public function scopePartial(Builder $q): Builder
    {
        return $q->where(function (Builder $q) {
            $q->where('remote_status', 'partial')->orWhere('status', 'partial');
        });
    }

    /**
     * Apply optional filters: date_from, date_to, provider_code, remote_service_id, user_login/user_remote_id.
     */
    public function scopeFilter(Builder $q, array $filters): Builder
    {
        if (!empty($filters['date_from'])) {
            $q->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->where('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['provider_code'])) {
            $q->where('provider_code', $filters['provider_code']);
        }
        if (isset($filters['remote_service_id']) && $filters['remote_service_id'] !== '') {
            $q->where('remote_service_id', $filters['remote_service_id']);
        }
        if (isset($filters['user_login']) && $filters['user_login'] !== '') {
            $q->where('user_login', $filters['user_login']);
        }
        if (isset($filters['user_remote_id']) && $filters['user_remote_id'] !== '') {
            $q->where('user_remote_id', $filters['user_remote_id']);
        }
        return $q;
    }
}
