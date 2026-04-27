<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalProvider extends Model
{
    protected $fillable = [
        'code',
        'name',
        'base_url',
        'api_key',
        'timeout',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'timeout' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
