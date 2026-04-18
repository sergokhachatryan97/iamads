<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTransaction extends Model
{
    use HasFactory;

    // Transaction type constants
    public const TYPE_ORDER_CHARGE = 'order_charge';
    public const TYPE_REFUND = 'refund';
    public const TYPE_SUBSCRIPTION_CHARGE = 'subscription_charge';
    public const TYPE_BALANCE_TOPUP = 'balance_topup';
    public const TYPE_MANUAL_CREDIT = 'manual_credit';
    public const TYPE_MANUAL_DEBIT = 'manual_debit';
    public const TYPE_REFERRAL_BONUS = 'referral_bonus';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'order_id',
        'payment_id',
        'amount',
        'type',
        'description',
        'is_test_balance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'is_test_balance' => 'boolean',
        ];
    }

    /**
     * Get the client that owns this transaction.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the order associated with this transaction.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the payment associated with this transaction (for balance top-up).
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
