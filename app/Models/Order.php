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
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_INVALID_LINK = 'invalid_link';
    public const STATUS_RESTRICTED = 'restricted';
    public const STATUS_AWAITING = 'awaiting';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PENDING_DEPENDENCY = 'pending_dependency';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_FAIL = 'fail';

    // Speed tier constants
    public const SPEED_TIER_NORMAL = 'normal';
    public const SPEED_TIER_FAST = 'fast';
    public const SPEED_TIER_SUPER_FAST = 'super_fast';

    // Dependency status constants
    public const DEPENDS_STATUS_OK = 'ok';
    public const DEPENDS_STATUS_FAILED = 'failed';
    public const DEPENDS_STATUS_UNKNOWN = 'unknown';

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
        'comment_text',
        'payment_source',
        'subscription_id',
        'charge',
        'cost',
        'quantity',
        'dripfeed_enabled',
        'dripfeed_quantity',
        'dripfeed_interval',
        'dripfeed_interval_unit',
        'dripfeed_runs_total',
        'dripfeed_interval_minutes',
        'dripfeed_run_index',
        'dripfeed_delivered_in_run',
        'dripfeed_next_run_at',
        'delivered',
        'remains',
        'start_count',
        'status',
        'speed_tier',
        'speed_multiplier',
        'depends_on_order_id',
        'depends_status',
        'depends_verified_at',
        'mode',
        'provider',
        'provider_order_id',
        'provider_sending_at',
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
            'dripfeed_enabled' => 'boolean',
            'dripfeed_quantity' => 'integer',
            'dripfeed_interval' => 'integer',
            'dripfeed_runs_total' => 'integer',
            'dripfeed_interval_minutes' => 'integer',
            'dripfeed_run_index' => 'integer',
            'dripfeed_delivered_in_run' => 'integer',
            'dripfeed_next_run_at' => 'datetime',
            'delivered' => 'integer',
            'remains' => 'integer',
            'start_count' => 'integer',
            'speed_multiplier' => 'decimal:2',
            'depends_on_order_id' => 'integer',
            'depends_verified_at' => 'datetime',
            'sent_to_provider_at' => 'datetime',
            'provider_payload' => 'array',
            'provider_response' => 'array',
            'provider_status_response' => 'array',
            'provider_last_error_at' => 'datetime',
            'provider_webhook_payload' => 'array',
            'provider_webhook_received_at' => 'datetime',
            'provider_sending_at' => 'datetime',
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

    /**
     * Get the order this order depends on.
     */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'depends_on_order_id');
    }

    /**
     * Get orders that depend on this order.
     */
    public function dependentOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'depends_on_order_id');
    }

    /**
     * Check if dependency is satisfied.
     *
     * @return bool
     */
    public function isDependencySatisfied(): bool
    {
        if (!$this->depends_on_order_id) {
            return true;
        }

        $dependency = $this->dependsOn;
        if (!$dependency) {
            return false;
        }

        // Dependency is satisfied if the required order is completed
        return in_array($dependency->status, [
            self::STATUS_COMPLETED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PROCESSING,
        ], true);
    }

    /**
     * Update dependency status.
     *
     * @return void
     */
    public function updateDependencyStatus(): void
    {
        if (!$this->depends_on_order_id) {
            return;
        }

        if ($this->isDependencySatisfied()) {
            $this->depends_status = self::DEPENDS_STATUS_OK;
        } else {
            $dependency = $this->dependsOn;
            if ($dependency && in_array($dependency->status, [
                self::STATUS_FAIL,
                self::STATUS_CANCELED,
                self::STATUS_INVALID_LINK,
            ], true)) {
                $this->depends_status = self::DEPENDS_STATUS_FAILED;
            } else {
                $this->depends_status = self::DEPENDS_STATUS_UNKNOWN;
            }
        }

        $this->depends_verified_at = now();
        $this->save();
    }
}
