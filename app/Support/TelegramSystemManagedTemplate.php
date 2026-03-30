<?php

namespace App\Support;

/**
 * Telegram service templates executed by the app (no performer claim / task lease).
 */
final class TelegramSystemManagedTemplate
{
    /**
     * @return list<string>
     */
    public static function templateKeys(): array
    {
        $cfg = config('telegram_service_templates', []);
        $keys = [];
        foreach ($cfg as $name => $row) {
            if ($name === 'premium_templates' || ! is_array($row)) {
                continue;
            }
            if (! empty($row['system_managed'])) {
                $keys[] = $name;
            }
        }

        return $keys;
    }

    public static function isSystemManagedTemplateKey(?string $templateKey): bool
    {
        if ($templateKey === null || $templateKey === '') {
            return false;
        }

        $row = config("telegram_service_templates.{$templateKey}");

        return is_array($row) && ! empty($row['system_managed']);
    }
}
