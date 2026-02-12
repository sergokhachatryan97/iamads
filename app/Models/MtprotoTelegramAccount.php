<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MtprotoTelegramAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'is_active',
        'subscription_count',
        'session_name',
        'last_used_at',
        'cooldown_until',
        'fail_count',
        'last_error_code',
        'last_error_at',
        'disabled_at',
        'phone_number',
        'is_probe',
        'proxy_type',
        'proxy_host',
        'proxy_port',
        'proxy_user',
        'proxy_pass',
        'proxy_secret',
        'force_proxy',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_probe' => 'boolean',
            'subscription_count' => 'integer',
            'last_used_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'fail_count' => 'integer',
            'last_error_at' => 'datetime',
            'disabled_at' => 'datetime',
            'proxy_port' => 'integer',
            'force_proxy' => 'boolean',
        ];
    }

    /**
     * Check if account is available for use.
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->disabled_at !== null) {
            return false;
        }

        if ($this->cooldown_until !== null && $this->cooldown_until->isFuture()) {
            return false;
        }

        return true;
    }


    /**
     * Record a failure.
     */
    public function recordFailure(string $errorCode): void
    {
        $this->increment('fail_count');
        $this->update([
            'last_error_code' => $errorCode,
            'last_error_at' => now(),
        ]);
    }

    /**
     * Record successful use.
     */
    public function recordSuccess(): void
    {
        $this->update([
            'last_used_at' => now(),
            'fail_count' => 0,
            'last_error_code' => null,
            'last_error_at' => null,
            'cooldown_until' => null,
        ]);
    }

    /**
     * Set cooldown until a specific time.
     */
    public function setCooldown(int $seconds): void
    {
        $this->update([
            'cooldown_until' => now()->addSeconds($seconds),
        ]);
    }

    /**
     * Disable account with error code.
     */
    public function disable(string $errorCode): void
    {
        $this->update([
            'is_active' => false,
            'disabled_at' => now(),
            'last_error_code' => $errorCode,
            'last_error_at' => now(),
        ]);
    }

    /**
     * Telegram account(s) linked to this MTProto session (provider/pull side).
     * Optional inverse of TelegramAccount::mtprotoAccount().
     */
    public function telegramAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TelegramAccount::class, 'mtproto_account_id');
    }

    /**
     * Get account setup tasks.
     */
    public function setupTasks(): HasMany
    {
        return $this->hasMany(MtprotoAccountTask::class, 'account_id');
    }

    /**
     * Get 2FA state for this account.
     */
    public function twoFactorState(): HasOne
    {
        return $this->hasOne(\App\Models\Mtproto2faState::class, 'account_id');
    }
}
