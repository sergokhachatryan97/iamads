<?php

namespace App\Services\App;

/**
 * Parses and normalizes Apple App Store and Google Play Store links.
 *
 * Google Play: https://play.google.com/store/apps/details?id=com.example.app&hl=en
 * Apple:       https://apps.apple.com/us/app/sea-battle-2/id913173849
 */
class AppLinkParser
{
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_IOS = 'ios';

    /**
     * Parse an app store URL and return normalized structured payload.
     *
     * @return array{
     *   ok: bool,
     *   platform?: 'android'|'ios',
     *   identifier?: string,
     *   normalized_url?: string,
     *   error?: string
     * }
     */
    public function parse(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['ok' => false, 'error' => 'App link is empty.'];
        }

        $parsed = parse_url($url);
        if (!isset($parsed['host']) || !isset($parsed['scheme'])) {
            return ['ok' => false, 'error' => 'Invalid URL format.'];
        }

        $host = strtolower($parsed['host']);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        if (str_contains($host, 'play.google.com')) {
            return $this->parseGooglePlay($url, $path, $query);
        }

        if (str_contains($host, 'apps.apple.com')) {
            return $this->parseAppleAppStore($url, $path);
        }

        return ['ok' => false, 'error' => 'Unsupported app store URL.'];
    }

    private function parseGooglePlay(string $inputUrl, string $path, string $query): array
    {
        if (!str_contains($path, '/store/apps/details')) {
            return ['ok' => false, 'error' => 'Invalid Google Play store URL.'];
        }

        parse_str($query, $params);
        $packageId = trim((string) ($params['id'] ?? ''));
        if ($packageId === '') {
            return ['ok' => false, 'error' => 'Google Play URL must contain package id (id= parameter).'];
        }

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+$/', $packageId)) {
            return ['ok' => false, 'error' => 'Invalid package name format.'];
        }

        $normalizedUrl = 'https://play.google.com/store/apps/details?id=' . $packageId;

        return [
            'ok' => true,
            'platform' => self::PLATFORM_ANDROID,
            'identifier' => $packageId,
            'normalized_url' => $normalizedUrl,
        ];
    }

    private function parseAppleAppStore(string $inputUrl, string $path): array
    {
        if (!preg_match('#/id(\d+)(?:\?|$|/)#', $path, $matches)) {
            return ['ok' => false, 'error' => 'Apple App Store URL must contain numeric app id (/id123456789).'];
        }

        $appId = $matches[1];
        $normalizedUrl = 'https://apps.apple.com/app/id' . $appId;

        return [
            'ok' => true,
            'platform' => self::PLATFORM_IOS,
            'identifier' => $appId,
            'normalized_url' => $normalizedUrl,
        ];
    }
}
