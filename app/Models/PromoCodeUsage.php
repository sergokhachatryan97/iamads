<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promo_code_id',
        'client_id',
        'amount_credited',
        'applied_at',
    ];

    protected $casts = [
        'amount_credited' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
