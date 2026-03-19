<?php

namespace App\Services\YouTube;

/**
 * Parses YouTube URLs and extracts link type and identifiers without calling the API.
 * Used to decide which YouTube Data API endpoint to call (videos.list vs channels.list).
 *
 * Supported URL patterns:
 * - youtube.com/watch?v=VIDEO_ID, youtu.be/VIDEO_ID, youtube.com/shorts/VIDEO_ID, youtube.com/embed/VIDEO_ID -> video
 * - youtube.com/live/VIDEO_ID -> stream (video-like)
 * - youtube.com/channel/UCxxxx -> channel (direct channel id)
 * - youtube.com/@handle -> channel (handle)
 * - youtube.com/c/customSlug -> channel (custom URL)
 * - youtube.com/user/username -> channel (legacy user URL)
 */
class YouTubeLinkParser
{
    public const LINK_TYPE_VIDEO = 'video';
    public const LINK_TYPE_STREAM = 'stream';
    public const LINK_TYPE_CHANNEL = 'channel';
    public const LINK_TYPE_CHANNEL_HANDLE = 'channel_handle';
    public const LINK_TYPE_CHANNEL_CUSTOM = 'channel_custom';
    public const LINK_TYPE_CHANNEL_USER = 'channel_user';

    public const ENTITY_VIDEO = 'video';
    public const ENTITY_CHANNEL = 'channel';

    /** URL pattern names for structured result */
    public const URL_PATTERN_WATCH = 'watch';
    public const URL_PATTERN_YOUTU_BE = 'youtu_be';
    public const URL_PATTERN_SHORTS = 'shorts';
    public const URL_PATTERN_EMBED = 'embed';
    public const URL_PATTERN_LIVE = 'live';
    public const URL_PATTERN_CHANNEL = 'channel';
    public const URL_PATTERN_HANDLE = 'handle';
    public const URL_PATTERN_CUSTOM = 'c';
    public const URL_PATTERN_USER = 'user';

    /**
     * Parse a YouTube URL and return link type, url_pattern, and extracted ids. No API calls.
     *
     * @return array{
     *   ok: bool,
     *   link_type: string,
     *   entity_kind: string,
     *   url_pattern?: string,
     *   video_id?: string,
     *   channel_id?: string,
     *   handle?: string,
     *   custom_slug?: string,
     *   username?: string,
     *   normalized_url?: string,
     *   is_stream?: bool,
     *   error_code?: string,
     *   error?: string
     * }
     */
    public function parse(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return [
                'ok' => false,
                'link_type' => '',
                'entity_kind' => '',
                'error_code' => 'EMPTY_LINK',
                'error' => 'YouTube link is empty',
            ];
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $host = strtolower((string) $host);
        if ($host !== 'www.youtube.com' && $host !== 'youtube.com' && $host !== 'm.youtube.com' && $host !== 'youtu.be') {
            return [
                'ok' => false,
                'link_type' => '',
                'entity_kind' => '',
                'error_code' => 'INVALID_DOMAIN',
                'error' => 'Not a YouTube URL',
            ];
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $path = '/' . trim($path, '/');
        $query = parse_url($url, PHP_URL_QUERY) ?: '';
        parse_str($query, $queryParams);

        // youtu.be/VIDEO_ID
        if ($host === 'youtu.be') {
            $path = trim($path, '/');
            if (preg_match('/^[\w-]{11}$/', $path)) {
                return [
                    'ok' => true,
                    'link_type' => self::LINK_TYPE_VIDEO,
                    'entity_kind' => self::ENTITY_VIDEO,
                    'url_pattern' => self::URL_PATTERN_YOUTU_BE,
                    'video_id' => $path,
                    'normalized_url' => 'https://www.youtube.com/watch?v=' . $path,
                    'is_stream' => false,
                ];
            }
            return $this->unsupportedUrl($url);
        }

        // youtube.com/channel/UCxxxx
        if (preg_match('#^/channel/(UC[\w-]{10,})#i', $path, $m)) {
            $channelId = strtoupper($m[1]);
            return [
                'ok' => true,
                'link_type' => self::LINK_TYPE_CHANNEL,
                'entity_kind' => self::ENTITY_CHANNEL,
                'url_pattern' => self::URL_PATTERN_CHANNEL,
                'channel_id' => $channelId,
                'normalized_url' => 'https://www.youtube.com/channel/' . $channelId,
            ];
        }

        // youtube.com/@handle
        if (preg_match('#^/@([\w.-]+)#i', $path, $m)) {
            $handle = $m[1];
            return [
                'ok' => true,
                'link_type' => self::LINK_TYPE_CHANNEL_HANDLE,
                'entity_kind' => self::ENTITY_CHANNEL,
                'url_pattern' => self::URL_PATTERN_HANDLE,
                'handle' => '@' . $handle,
                'normalized_url' => 'https://www.youtube.com/@' . $handle,
            ];
        }

        // youtube.com/c/customSlug
        if (preg_match('#^/c/([\w.-]+)#i', $path, $m)) {
            return [
                'ok' => true,
                'link_type' => self::LINK_TYPE_CHANNEL_CUSTOM,
                'entity_kind' => self::ENTITY_CHANNEL,
                'url_pattern' => self::URL_PATTERN_CUSTOM,
                'custom_slug' => $m[1],
                'normalized_url' => 'https://www.youtube.com/c/' . $m[1],
            ];
        }
        // youtube.com/user/username
        if (preg_match('#^/user/([\w.-]+)#i', $path, $m)) {
            return [
                'ok' => true,
                'link_type' => self::LINK_TYPE_CHANNEL_USER,
                'entity_kind' => self::ENTITY_CHANNEL,
                'url_pattern' => self::URL_PATTERN_USER,
                'username' => $m[1],
                'normalized_url' => 'https://www.youtube.com/user/' . $m[1],
            ];
        }

        // youtube.com/live/VIDEO_ID
        if (preg_match('#^/live/([\w-]{11})#i', $path, $m)) {
            return [
                'ok' => true,
                'link_type' => self::LINK_TYPE_STREAM,
                'entity_kind' => self::ENTITY_VIDEO,
                'url_pattern' => self::URL_PATTERN_LIVE,
                'video_id' => $m[1],
                'normalized_url' => 'https://www.youtube.com/watch?v=' . $m[1],
                'is_stream' => true,
            ];
        }

        // youtube.com/watch?v=VIDEO_ID
        if (preg_match('#^/watch#i', $path)) {
            $v = isset($queryParams['v']) && preg_match('/^[\w-]{11}$/', (string) $queryParams['v'])
                ? (string) $queryParams['v']
                : null;
            if ($v !== null) {
                return [
                    'ok' => true,
                    'link_type' => self::LINK_TYPE_VIDEO,
                    'entity_kind' => self::ENTITY_VIDEO,
                    'url_pattern' => self::URL_PATTERN_WATCH,
                    'video_id' => $v,
                    'normalized_url' => 'https://www.youtube.com/watch?v=' . $v,
                    'is_stream' => false,
                ];
            }
        }
        // youtube.com/shorts/VIDEO_ID or /embed/VIDEO_ID
        if (preg_match('#^/shorts/([\w-]{11})#i', $path, $m)) {
            return [
                'ok' => true,
                'link_type' => self::LINK_TYPE_VIDEO,
                'entity_kind' => self::ENTITY_VIDEO,
                'url_pattern' => self::URL_PATTERN_SHORTS,
                'video_id' => $m[1],
                'normalized_url' => 'https://www.youtube.com/watch?v=' . $m[1],
                'is_stream' => false,
            ];
        }
        if (preg_match('#^/embed/([\w-]{11})#i', $path, $m)) {
            return [
                'ok' => true,
                'link_type' => self::LINK_TYPE_VIDEO,
                'entity_kind' => self::ENTITY_VIDEO,
                'url_pattern' => self::URL_PATTERN_EMBED,
                'video_id' => $m[1],
                'normalized_url' => 'https://www.youtube.com/watch?v=' . $m[1],
                'is_stream' => false,
            ];
        }

        return $this->unsupportedUrl($url);
    }

    private function unsupportedUrl(string $url): array
    {
        return [
            'ok' => false,
            'link_type' => '',
            'entity_kind' => '',
            'error_code' => 'UNSUPPORTED_URL',
            'error' => 'Unsupported YouTube URL pattern',
        ];
    }
}
