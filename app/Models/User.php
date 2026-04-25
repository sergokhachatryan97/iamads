<?php

namespace App\Models;

use App\Notifications\VerifyEmailWithStaffRoute;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected string $guard_name = 'staff';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'referral_code',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailWithStaffRoute);
    }

    /**
     * Get the user's avatar URL.
     *
     * @return string
     */
    /**
     * Clients assigned to this staff member.
     */
    public function assignedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'staff_id');
    }

    /**
     * Clients referred by this staff member via referral link.
     */
    public function referredClients(): HasMany
    {
        return $this->hasMany(Client::class, 'referred_by_staff_id');
    }

    /**
     * Ensure the staff user has a referral code.
     */
    public function ensureReferralCode(): string
    {
        if (!$this->referral_code) {
            do {
                $code = strtoupper(Str::random(8));
            } while (static::where('referral_code', $code)->exists());

            $this->update(['referral_code' => $code]);
        }

        return $this->referral_code;
    }

    /**
     * Get the full referral URL for this staff member.
     */
    public function getReferralUrlAttribute(): string
    {
        return url('/r/' . $this->ensureReferralCode());
    }

    /**
     * Whether this user can access all clients and orders (not limited to assigned ones).
     */
    public function canAccessAllClients(): bool
    {
        return $this->hasRole('super_admin') || $this->hasPermissionTo('clients.access-all', 'staff');
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }

        // Return default avatar (using Gravatar or initials)
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=random';
    }
}
