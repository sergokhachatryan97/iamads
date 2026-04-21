<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'api_enabled',
        'api_key',
        'api_key_generated_at',
        'api_last_used_at',
        'referral_code',
        'referred_by',
        'referred_by_staff_id',
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
        'api_key',
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
            'api_enabled' => 'boolean',
            'api_key_generated_at' => 'datetime',
            'api_last_used_at' => 'datetime',
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
     * Get the payments for this client.
     */
    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
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

    /**
     * Get the client who referred this client.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referred_by');
    }

    /**
     * Get the staff member who referred this client via admin referral link.
     */
    public function referredByStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_staff_id');
    }

    /**
     * Get the clients referred by this client.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Client::class, 'referred_by');
    }

    /**
     * Ensure the client has a referral code, generating one if needed.
     */
    public function ensureReferralCode(): string
    {
        if (!$this->referral_code) {
            $this->referral_code = $this->generateUniqueReferralCode();
            $this->save();
        }

        return $this->referral_code;
    }

    private function generateUniqueReferralCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }
}
