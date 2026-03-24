<?php

namespace App\Support\App;

use App\Models\Order;

/**
 * Canonical app target for uniqueness (one claim per account + app link + action).
 */
class AppTargetNormalizer
{
    /**
     * Get target hash from order's provider_payload.app or link.
     */
    public static function targetHash(Order $order): string
    {
        $payload = $order->provider_payload ?? [];
        $app = is_array($payload['app'] ?? null) ? $payload['app'] : [];
        $targetHash = $app['target_hash'] ?? null;

        if (is_string($targetHash) && $targetHash !== '') {
            return $targetHash;
        }

        return self::linkHash(trim((string) ($order->link ?? '')));
    }

    public static function linkHash(string $link): string
    {
        return hash('sha256', strtolower(trim($link)));
    }
}
