<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Mtproto2faState extends Model
{
    use HasFactory;

    protected $table = 'mtproto_2fa_states';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_WAITING_EMAIL = 'waiting_email';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'account_id',
        'email_alias',
        'encrypted_password',
        'status',
        'last_error',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the account this 2FA state belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MtprotoTelegramAccount::class, 'account_id');
    }

    /**
     * Set password (encrypts before storing).
     */
    public function setPassword(string $password): void
    {
        $this->encrypted_password = Crypt::encryptString($password);
    }

    /**
     * Get password (decrypts from storage).
     */
    public function getPassword(): string
    {
        return Crypt::decryptString($this->encrypted_password);
    }

    /**
     * Check if 2FA is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Check if 2FA is in a final state (confirmed or failed).
     */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_FAILED], true);
    }
}
