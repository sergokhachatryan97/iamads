<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLoginLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'signed_in_at',
        'ip',
        'user_agent',
        'country',
        'city',
        'lat',
        'lng',
        'device_type',
        'os',
        'browser',
        'device_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signed_in_at' => 'datetime',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    /**
     * Get the client that owns this login log.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}