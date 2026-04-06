<?php

namespace App\Support\Links;

/**
 * Normalizes order link input before validation.
 *
 * - Leaves @handles unchanged (Telegram, etc.) — never prefixes https://
 * - MAX bare usernames when driver is max
 * - Otherwise prepends https:// for scheme-less URLs (e.g. t.me/…)
 */
final class OrderLinkNormalizer
{
    public static function normalize(string $link, string $linkDriver = 'generic'): string
    {
        $link = trim($link);
        if ($link === '' || preg_match('#^https?://#i', $link)) {
            return $link;
        }

        if (str_starts_with($link, '@')) {
            return $link;
        }

        if (str_starts_with($link, 'tg://')) {
            return $link;
        }

        if ($linkDriver === 'max' && preg_match('/^[a-zA-Z0-9_]{3,64}$/', $link)) {
            return $link;
        }

        return 'https://'.$link;
    }
}
