<?php

namespace App\Support;

/**
 * Validates order links by category/service link type (telegram, youtube, vk, other).
 * Used by HomeController and anywhere link format must be checked server-side.
 */
final class LinkValidator
{
    public const TYPE_TELEGRAM = 'telegram';
    public const TYPE_YOUTUBE = 'youtube';
    public const TYPE_MAX = 'max';
    public const TYPE_OTHER = 'other';

    /**
     * Infer link type from category name (e.g. "Telegram", "YouTube views", "VK").
     */
    public static function linkTypeFromCategoryName(?string $categoryName): string
    {
        if ($categoryName === null || $categoryName === '') {
            return self::TYPE_OTHER;
        }
        $name = $categoryName;
        if (stripos($name, 'youtube') !== false) {
            return self::TYPE_YOUTUBE;
        }
        if (stripos($name, 'max') !== false) {
            return self::TYPE_MAX;
        }
        if (stripos($name, 'telegram') !== false) {
            return self::TYPE_TELEGRAM;
        }
        return self::TYPE_OTHER;
    }

    /**
     * Validate a link for the given link type.
     *
     * @return array{valid: bool, error: string|null} error is a translation key or message when valid is false
     */
    public static function validate(string $link, string $linkType): array
    {
        $link = trim($link);
        if ($link === '') {
            return ['valid' => false, 'error' => __('home.link_error')];
        }

        return match ($linkType) {
            self::TYPE_TELEGRAM => self::validateTelegram($link),
            self::TYPE_YOUTUBE => self::validateYoutube($link),
            self::TYPE_MAX => self::validateMax($link),
            self::TYPE_OTHER => self::validateOther($link),
            default => self::validateTelegram($link),
        };
    }

    private static function validateTelegram(string $link): array
    {
        $parsed = TelegramLinkParser::parse($link);
        $kind = $parsed['kind'] ?? 'unknown';

        if ($kind === 'unknown') {
            return ['valid' => false, 'error' => __('Invalid Telegram link format.')];
        }
        if ($kind === 'special') {
            return ['valid' => false, 'error' => __('Link is not a joinable chat.')];
        }
        if ($kind === 'private_post') {
            return ['valid' => false, 'error' => __('Private post links are not supported.')];
        }

        return ['valid' => true, 'error' => null];
    }

    private static function validateYoutube(string $link): array
    {
        $ok = (bool) preg_match(
            '~^(https?://)?(www\.)?(youtube\.com/(watch\?v=[A-Za-z0-9_\-]+(\&[^&\s#]+)*|shorts/[A-Za-z0-9_\-]+|embed/[A-Za-z0-9_\-]+|live/[A-Za-z0-9_\-]+)|youtube\.com/@[A-Za-z0-9_.\-]+|youtube\.com/channel/UC[A-Za-z0-9_\-]+|youtube\.com/c/[A-Za-z0-9_.\-]+|youtu\.be/[A-Za-z0-9_\-]+(\?[^\s#]*)?)~i',
            $link
        );
        return $ok
            ? ['valid' => true, 'error' => null]
            : ['valid' => false, 'error' => __('home.link_error_youtube')];
    }

    private static function validateMax(string $link): array
    {
        $inspector = new \App\Support\Links\Inspectors\MaxLinkInspector();
        $result = $inspector->inspect($link);

        return [
            'valid' => $result['valid'],
            'error' => $result['error'] ?? null,
        ];
    }

    private static function validateOther(string $link): array
    {
        if (strlen($link) < 5) {
            return ['valid' => false, 'error' => __('home.link_error_other')];
        }
        $hasProtocol = (bool) preg_match('#^https?://#i', $link);
        $looksLikeDomain = (bool) preg_match('#^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}#', $link);
        $ok = $hasProtocol || $looksLikeDomain;
        return $ok
            ? ['valid' => true, 'error' => null]
            : ['valid' => false, 'error' => __('home.link_error_other')];
    }
}
