<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Centralizes premium Telegram service template keys and scope filtering for provider claim/report APIs.
 */
final class TelegramPremiumTemplateScope
{
    public const SCOPE_DEFAULT = 'default';

    public const SCOPE_PREMIUM = 'premium';

    /**
     * @return list<string>
     */
    public static function premiumTemplateKeys(): array
    {
        return config('telegram_service_templates.premium_templates', []);
    }

    public static function isPremiumTemplateKey(?string $templateKey): bool
    {
        if ($templateKey === null || $templateKey === '') {
            return false;
        }

        return in_array($templateKey, self::premiumTemplateKeys(), true);
    }

    public static function orderMatchesScope(Order $order, string $scope): bool
    {
        $order->loadMissing('service');

        return self::isPremiumTemplateKey($order->service?->template_key)
            ? $scope === self::SCOPE_PREMIUM
            : $scope === self::SCOPE_DEFAULT;
    }

    /**
     * Restrict a services relation query to default (non-premium) or premium template_key sets.
     *
     * @param  Builder<\App\Models\Service>  $serviceQuery
     */
    public static function applyServiceTemplateScope(Builder $serviceQuery, string $scope): void
    {
        $keys = self::premiumTemplateKeys();

        if ($scope === self::SCOPE_PREMIUM) {
            $serviceQuery->whereIn('template_key', $keys);

            return;
        }

        if ($keys === []) {
            return;
        }

        $serviceQuery->where(function ($q) use ($keys): void {
            $q->whereNull('template_key')->orWhereNotIn('template_key', $keys);
        });
    }

    /**
     * Keys from telegram_service_templates that are actual service definitions (excludes meta keys like premium_templates).
     *
     * @return list<string>
     */
    public static function selectableTelegramTemplateKeys(): array
    {
        $cfg = config('telegram_service_templates', []);

        return array_values(array_filter(
            array_keys($cfg),
            static fn (string $k): bool => $k !== 'premium_templates'
        ));
    }
}
