<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientServiceQuota extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'subscription_id',
        'service_id',
        'orders_left',
        'quantity_left',
        'link',
        'expires_at',
        'auto_renew',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orders_left' => 'integer',
            'quantity_left' => 'integer',
            'expires_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    /**
     * Get the client that owns this quota.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the subscription plan for this quota.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_id');
    }

    /**
     * Get the service for this quota.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
