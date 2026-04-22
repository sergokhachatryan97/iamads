<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'is_active',
        'created_by',
        'reward_value',
        'max_uses',
        'used_count',
        'max_uses_per_client',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reward_value' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'max_uses_per_client' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    public function isUsable(): bool
    {
        return $this->is_active && !$this->isExpired() && !$this->isExhausted();
    }

    public function usageCountForClient(int $clientId): int
    {
        return $this->usages()->where('client_id', $clientId)->count();
    }
}
