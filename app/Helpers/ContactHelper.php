<?php

namespace App\Helpers;

class ContactHelper
{
    /**
     * Get the Telegram support username for the current locale.
     * Falls back to the default contact.telegram config.
     */
    public static function telegram(): string
    {
        $locale = app()->getLocale();
        $byLocale = config('contact.telegram_by_locale', []);

        $username = $byLocale[$locale] ?? config('contact.telegram', '');

        return ltrim(trim((string) $username), '@');
    }
}
