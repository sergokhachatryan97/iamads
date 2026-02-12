<?php

namespace App\Services\Telegram;

/**
 * Generates cryptographically secure unique passwords for Telegram account 2FA.
 *
 * Uses CSPRNG (random_bytes) to ensure strong randomness.
 */
class AccountPasswordGenerator
{
    /**
     * Default password length.
     */
    private int $length;

    /**
     * Character set: uppercase, lowercase, digits.
     * Excludes ambiguous characters (0, O, I, l, 1) for better readability.
     */
    private const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public function __construct(?int $length = null)
    {
        $this->length = $length ?? (int) config('telegram.account_password.length', 20);
    }

    /**
     * Generate a cryptographically secure unique password.
     *
     * @return string
     */
    public function generate(): string
    {
        $password = '';
        $charsetLength = strlen(self::CHARSET);

        // Use random_bytes for cryptographically secure randomness
        $randomBytes = random_bytes($this->length);

        for ($i = 0; $i < $this->length; $i++) {
            // Convert byte to index in charset using modulo
            $index = ord($randomBytes[$i]) % $charsetLength;
            $password .= self::CHARSET[$index];
        }

        return $password;
    }
}
