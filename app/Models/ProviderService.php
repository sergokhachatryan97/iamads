<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderService extends Model
{
    protected $fillable = [
        'provider_code',
        'remote_service_id',
        'name',
        'type',
        'category',
        'rate',
        'min',
        'max',
        'refill',
        'cancel',
        'currency',
        'is_active',
        'description'
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'min' => 'integer',
        'max' => 'integer',
        'refill' => 'boolean',
        'cancel' => 'boolean',
        'is_active' => 'boolean',
        'description' => 'array',
    ];
}
