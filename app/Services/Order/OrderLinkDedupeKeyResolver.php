<?php

namespace App\Services\Order;

use App\Support\TelegramLinkParser;

/**
 * Resolves a canonical key for a link for duplicate detection within the same
 * order/request. Telegram uses TelegramLinkParser (unchanged); other drivers
 * use a normalized raw string so exact duplicates are still detected.
 */
class OrderLinkDedupeKeyResolver
{
    /**
     * Return a stable key for the given link so duplicates in the same request
     * can be detected. For telegram, uses the same logic as before (username,
     * invite hash, etc.). For other drivers, uses normalized raw link.
     */
    public function getKey(string $driver, string $rawLink): string
    {
        $raw = trim($rawLink);

        if ($driver === 'telegram') {
            return $this->telegramKey($raw);
        }

        return 'raw:'.strtolower($raw);
    }

    /**
     * Telegram key logic: unchanged from original OrderService behavior.
     */
    private function telegramKey(string $raw): string
    {
        $parsed = TelegramLinkParser::parse($raw);
        $kind = $parsed['kind'] ?? 'unknown';

        return match ($kind) {
            'public_username', 'bot_start' => $kind.':'.strtolower((string) ($parsed['username'] ?? '')),

            'public_post' => $kind.':'.strtolower((string) ($parsed['username'] ?? '')).':'.(string) ($parsed['post_id'] ?? ''),

            'invite' => $kind.':'.(string) ($parsed['hash'] ?? ''),

            default => 'raw:'.strtolower($raw),
        };
    }
}
