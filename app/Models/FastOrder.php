<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FastOrder extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'category_id',
        'service_id',
        'payload',
        'status',
        'payment_method',
        'payment_status',
        'payment_reference',
        'total_amount',
        'currency',
        'generated_email',
        'client_id',
        'order_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'total_amount' => 'decimal:4',
            'expires_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function getOrderPayload(): array
    {
        return $this->payload;
    }
}
