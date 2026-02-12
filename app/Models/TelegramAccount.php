<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;

class TelegramAccount extends Model
{
    use HasFactory;

    protected $table = 'telegram_accounts';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        // identity
        'mtproto_account_id',
        'provider_account_id',
        'phone',
        'api_id',
        'api_hash',
        'name',

        // session
        'session_storage',
        'session_path',
        'session_string',
        'dc_id',
        'proxy',

        // state
        'is_active',
        'status',
        'disabled_until',

        'last_used_at',
        'last_ok_at',
        'last_error_at',
        'last_error',
        'fail_count',

        // limits
        'subscription_count',
        'max_subscriptions',
        'weight',
        'tags',
        'meta',

        // onboarding
        'onboarding_status',
        'onboarding_step',
        'onboarding_last_error',
        'onboarding_last_task_id',
        'onboarding_request_seed',

        // 2FA / profile
        'twofa_password_encrypted',
        'desired_profile_name',
        'desired_profile_name_norm',
        'profile_name_changed_at',

        // visibility
        'is_visible',
        'should_hide_after_name_change',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'should_hide_after_name_change' => 'boolean',

        'disabled_until' => 'datetime',
        'last_used_at' => 'datetime',
        'last_ok_at' => 'datetime',
        'last_error_at' => 'datetime',
        'profile_name_changed_at' => 'datetime',

        'proxy' => 'array',
        'tags' => 'array',
        'meta' => 'array',
    ];

    /**
     * Normalize profile name for uniqueness checks.
     */
    private static function normalizeProfileName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }
        // Trim, collapse multiple spaces to single space, lowercase
        return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    /**
     * Boot method to auto-normalize desired_profile_name on save.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (TelegramAccount $account) {
            if ($account->isDirty('desired_profile_name')) {
                $account->desired_profile_name_norm = self::normalizeProfileName($account->desired_profile_name);
            }
        });
    }

    /**
     * -----------------------------
     * 2FA helpers
     * -----------------------------
     */

    public function setTwoFaPassword(string $plainPassword): void
    {
        $this->twofa_password_encrypted = Crypt::encryptString($plainPassword);
    }

    public function getTwoFaPassword(): ?string
    {
        if (!$this->twofa_password_encrypted) {
            return null;
        }

        return Crypt::decryptString($this->twofa_password_encrypted);
    }

    public function hasTwoFaPassword(): bool
    {
        return !empty($this->twofa_password_encrypted);
    }

    /**
     * -----------------------------
     * Onboarding helpers
     * -----------------------------
     */

    public function markQueued(string $step): void
    {
        $this->update([
            'onboarding_status' => 'queued',
            'onboarding_step' => $step,
            'onboarding_last_error' => null,
        ]);
    }

    public function markInProgress(): void
    {
        $this->update([
            'onboarding_status' => 'in_progress',
            'onboarding_last_error' => null,
        ]);
    }

    public function markDone(): void
    {
        $this->update([
            'onboarding_status' => 'done',
            'onboarding_step' => null,
            'onboarding_last_error' => null,
            'onboarding_last_task_id' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'onboarding_status' => 'failed',
            'onboarding_last_error' => $error,
            'last_error' => $error,
            'last_error_at' => now(),
            'fail_count' => $this->fail_count + 1,
        ]);
    }

    /**
     * -----------------------------
     * Profile name helpers
     * -----------------------------
     */

    public function hasDesiredProfileName(): bool
    {
        return !empty($this->desired_profile_name);
    }

    public function markProfileNameChanged(): void
    {
        $this->update([
            'profile_name_changed_at' => now(),
        ]);
    }

    public function profileNameWasChanged(): bool
    {
        return !empty($this->profile_name_changed_at);
    }

    /**
     * -----------------------------
     * Visibility helpers
     * -----------------------------
     */

    public function hide(): void
    {
        $this->update(['is_visible' => false]);
    }

    public function show(): void
    {
        $this->update(['is_visible' => true]);
    }

    /**
     * -----------------------------
     * Relations
     * -----------------------------
     */

    /**
     * Linked MTProto session for local MadelineProto execution (manual-mode services).
     */
    public function mtprotoAccount(): BelongsTo
    {
        return $this->belongsTo(MtprotoTelegramAccount::class, 'mtproto_account_id');
    }

    /**
     * -----------------------------
     * Scopes
     * -----------------------------
     */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNeedsOnboarding($query)
    {
        return $query->whereIn('onboarding_status', ['new', 'failed']);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * -----------------------------
     * Convenience
     * -----------------------------
     */

    public function canBeUsed(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->disabled_until && Carbon::now()->lt($this->disabled_until)) {
            return false;
        }

        if ($this->status !== 'ready') {
            return false;
        }

        return true;
    }
}
