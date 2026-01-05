<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Client extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'balance',
        'spent',
        'discount',
        'rates',
        'staff_id',
        'last_auth',
        'status',
        'email_verified_at',
        'suspended_at',
        'malicious_at',
        'provider',
        'provider_id',
        'avatar',
        'social_media',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'balance' => 'decimal:8',
            'spent' => 'decimal:8',
            'discount' => 'decimal:2',
            'rates' => 'array',
            'last_auth' => 'datetime',
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'malicious_at' => 'datetime',
            'social_media' => 'array',
        ];
    }

    /**
     * Get the staff member who manages this client.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Get the services favorited by this client.
     */
    public function favoriteServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'client_service_favorites', 'client_id', 'service_id')
            ->withTimestamps()
            ->select('services.*');
    }

    /**
     * Get the login logs for this client.
     */
    public function loginLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientLoginLog::class);
    }

    /**
     * Get the orders for this client.
     */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the transactions for this client.
     */
    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientTransaction::class);
    }

    /**
     * Get the service quotas for this client.
     */
    public function serviceQuotas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientServiceQuota::class);
    }

    /**
     * Get the service limits for this client.
     */
    public function serviceLimits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientServiceLimit::class);
    }
}
