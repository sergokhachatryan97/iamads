<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    // Payment source constants
    public const PAYMENT_SOURCE_BALANCE = 'balance';
    public const PAYMENT_SOURCE_SUBSCRIPTION = 'subscription';

    // Status constants
    public const STATUS_AWAITING = 'awaiting';
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_FAIL = 'fail';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'batch_id',
        'client_id',
        'created_by',
        'category_id',
        'service_id',
        'link',
        'payment_source',
        'subscription_id',
        'charge',
        'cost',
        'quantity',
        'delivered',
        'remains',
        'start_count',
        'status',
        'mode',
        'provider_order_id',
        'sent_to_provider_at',
        'provider_payload',
        'provider_response',
        'provider_status_response',
        'provider_last_error',
        'provider_last_error_at',
        'provider_webhook_payload',
        'provider_webhook_received_at',
        'provider_webhook_last_error',
        'provider_last_polled_at',
        'provider_status_sync_lock_at',
        'provider_status_sync_lock_owner',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charge' => 'decimal:2',
            'cost' => 'decimal:2',
            'quantity' => 'integer',
            'delivered' => 'integer',
            'remains' => 'integer',
            'start_count' => 'integer',
            'sent_to_provider_at' => 'datetime',
            'provider_payload' => 'array',
            'provider_response' => 'array',
            'provider_status_response' => 'array',
            'provider_last_error_at' => 'datetime',
            'provider_webhook_payload' => 'array',
            'provider_webhook_received_at' => 'datetime',
            'provider_last_polled_at' => 'datetime',
            'provider_status_sync_lock_at' => 'datetime',
        ];
    }

    /**
     * Get the client that owns this order.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who created this order (if created by admin/staff).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the category for this order.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the service for this order.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the subscription plan (if paid by subscription).
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_id');
    }


    /**
     * Get the transactions for this order.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(ClientTransaction::class);
    }
}
