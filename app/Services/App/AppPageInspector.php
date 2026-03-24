<?php

namespace App\Services\App;

use Illuminate\Support\Facades\Http;

/**
 * Inspects app store pages: verifies reachability and optionally extracts
 * public download/install range metadata (Android only).
 */
class AppPageInspector
{
    public const DOWNLOADS_VISIBILITY_PUBLIC_RANGE = 'public_range';
    public const DOWNLOADS_VISIBILITY_UNAVAILABLE = 'unavailable';

    public const DOWNLOADS_SOURCE_PUBLIC_STORE_PAGE = 'public_store_page';
    public const DOWNLOADS_SOURCE_NONE = 'none';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const ACCEPT_LANGUAGE = 'en-US,en;q=0.9';

    private const PLAY_STORE_RANGE_PATTERN = '/(\d+(?:\.\d+)?[KMB]\+?)\s*(?:downloads?|installs?)/ui';

    public function __construct(
        private AppLinkParser $parser
    ) {}

    /**
     * Inspect app page: parse URL, fetch page, verify validity, optionally extract metadata.
     *
     * @return array{
     *   ok: bool,
     *   platform?: string,
     *   identifier?: string,
     *   normalized_url?: string,
     *   downloads_visibility?: string,
     *   downloads_range_label?: string|null,
     *   downloads_source?: string,
     *   error?: string
     * }
     */
    public function inspect(string $url): array
    {
        $parsed = $this->parser->parse($url);
        if (!($parsed['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => $parsed['error'] ?? 'Could not parse app URL.',
            ];
        }

        $platform = $parsed['platform'];
        $normalizedUrl = $parsed['normalized_url'];

        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept-Language' => self::ACCEPT_LANGUAGE,
            'Accept' => 'text/html,application/xhtml+xml',
        ])->timeout(15)->get($normalizedUrl);

        if (!$response->successful()) {
            return [
                'ok' => false,
                'error' => 'App page is not reachable.',
                'platform' => $platform,
                'identifier' => $parsed['identifier'] ?? null,
                'normalized_url' => $normalizedUrl,
            ];
        }

        $html = $response->body();

        if ($platform === AppLinkParser::PLATFORM_ANDROID) {
            return $this->inspectAndroid($parsed, $html);
        }

        return $this->inspectIos($parsed, $html);
    }

    private function inspectAndroid(array $parsed, string $html): array
    {
        $isValidPage = $this->looksLikeGooglePlayAppPage($html);
        if (!$isValidPage) {
            return [
                'ok' => false,
                'error' => 'Page does not appear to be a valid Google Play app listing.',
                'platform' => AppLinkParser::PLATFORM_ANDROID,
                'identifier' => $parsed['identifier'] ?? null,
                'normalized_url' => $parsed['normalized_url'] ?? null,
            ];
        }

        $rangeLabel = $this->extractDownloadsRangeLabel($html);

        return [
            'ok' => true,
            'platform' => AppLinkParser::PLATFORM_ANDROID,
            'identifier' => $parsed['identifier'] ?? null,
            'normalized_url' => $parsed['normalized_url'] ?? null,
            'downloads_visibility' => $rangeLabel !== null ? self::DOWNLOADS_VISIBILITY_PUBLIC_RANGE : self::DOWNLOADS_VISIBILITY_UNAVAILABLE,
            'downloads_range_label' => $rangeLabel,
            'downloads_source' => $rangeLabel !== null ? self::DOWNLOADS_SOURCE_PUBLIC_STORE_PAGE : self::DOWNLOADS_SOURCE_NONE,
            'error' => null,
        ];
    }

    private function inspectIos(array $parsed, string $html): array
    {
        $isValidPage = $this->looksLikeAppleAppStorePage($html);
        if (!$isValidPage) {
            return [
                'ok' => false,
                'error' => 'Page does not appear to be a valid Apple App Store app listing.',
                'platform' => AppLinkParser::PLATFORM_IOS,
                'identifier' => $parsed['identifier'] ?? null,
                'normalized_url' => $parsed['normalized_url'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'platform' => AppLinkParser::PLATFORM_IOS,
            'identifier' => $parsed['identifier'] ?? null,
            'normalized_url' => $parsed['normalized_url'] ?? null,
            'downloads_visibility' => self::DOWNLOADS_VISIBILITY_UNAVAILABLE,
            'downloads_range_label' => null,
            'downloads_source' => self::DOWNLOADS_SOURCE_NONE,
            'error' => null,
        ];
    }

    private function looksLikeGooglePlayAppPage(string $html): bool
    {
        $indicators = [
            'play.google.com',
            'itemprop="name"',
            'og:type',
            'application',
        ];
        $htmlLower = strtolower($html);
        $matchCount = 0;
        foreach ($indicators as $indicator) {
            if (str_contains($htmlLower, strtolower($indicator))) {
                $matchCount++;
            }
        }
        return $matchCount >= 2;
    }

    private function looksLikeAppleAppStorePage(string $html): bool
    {
        $indicators = [
            'apps.apple.com',
            'og:type',
            'software',
        ];
        $htmlLower = strtolower($html);
        foreach ($indicators as $indicator) {
            if (str_contains($htmlLower, strtolower($indicator))) {
                return true;
            }
        }
        return str_contains($htmlLower, 'apple.com') && (str_contains($htmlLower, 'app') || str_contains($htmlLower, 'software'));
    }

    private function extractDownloadsRangeLabel(string $html): ?string
    {
        if (preg_match(self::PLAY_STORE_RANGE_PATTERN, $html, $m)) {
            $label = trim($m[1]);
            if (preg_match('/^\d+(?:\.\d+)?[KMB]\+?$/i', $label)) {
                return $label;
            }
        }
        $patterns = [
            '/(\d+[\d.]*\s*[KMB]\+?)\s*downloads?/ui',
            '/(\d+[\d.]*\s*[KMB]\+?)\s*installs?/ui',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                return preg_replace('/\s+/', '', $m[1]);
            }
        }
        return null;
    }
}
