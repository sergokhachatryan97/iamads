<?php

namespace App\Support\YouTube;

use App\Models\Order;

/**
 * Canonical YouTube subscribe target for global uniqueness across URL shapes.
 * Prefer provider_payload.youtube.channel_id when set (UC…).
 */
class YouTubeTargetNormalizer
{
    /**
     * Resolve target for stateful actions (subscribe) from order link + optional payload.
     *
     * @return array{target_type: string, normalized_target: string, target_hash: string}
     */
    public static function forSubscribeTarget(Order $order): array
    {
        $payload = $order->provider_payload ?? [];
        $yt = is_array($payload['youtube'] ?? null) ? $payload['youtube'] : [];
        $meta = is_array($payload['execution_meta'] ?? null) ? $payload['execution_meta'] : [];

        $channelId = $yt['channel_id'] ?? $meta['youtube_channel_id'] ?? null;
        if (is_string($channelId) && preg_match('/^UC[\w-]{10,}$/', trim($channelId))) {
            $id = strtoupper(trim($channelId));
            return [
                'target_type' => 'channel_id',
                'normalized_target' => $id,
                'target_hash' => hash('sha256', 'yt:channel:' . $id),
            ];
        }

        $link = trim((string) ($order->link ?? ''));
        if ($link === '') {
            return [
                'target_type' => 'empty',
                'normalized_target' => '',
                'target_hash' => hash('sha256', 'yt:empty'),
            ];
        }

        return self::parseUrl($link);
    }

    /**
     * Parse public YouTube URL into a stable target identity.
     */
    public static function parseUrl(string $url): array
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if ($host === 'youtu.be') {
            $path = trim($parts['path'] ?? '', '/');
            if ($path !== '' && preg_match('/^[\w-]{11}$/', $path)) {
                return [
                    'target_type' => 'video_id',
                    'normalized_target' => $path,
                    'target_hash' => hash('sha256', 'yt:video:' . $path),
                ];
            }
        }

        $path = $parts['path'] ?? '/';
        $path = '/' . trim($path, '/');

        if (preg_match('#^/channel/(UC[\w-]{10,})#i', $path, $m)) {
            $id = strtoupper($m[1]);
            return [
                'target_type' => 'channel_id',
                'normalized_target' => $id,
                'target_hash' => hash('sha256', 'yt:channel:' . $id),
            ];
        }

        if (preg_match('#^/@([\w.-]+)#i', $path, $m)) {
            $handle = strtolower($m[1]);
            return [
                'target_type' => 'handle',
                'normalized_target' => '@' . $handle,
                'target_hash' => hash('sha256', 'yt:handle:@' . $handle),
            ];
        }

        if (preg_match('#^/c/([\w.-]+)#i', $path, $m)) {
            $slug = strtolower($m[1]);
            return [
                'target_type' => 'custom_slug',
                'normalized_target' => $slug,
                'target_hash' => hash('sha256', 'yt:c:' . $slug),
            ];
        }

        if (preg_match('#^/user/([\w.-]+)#i', $path, $m)) {
            $user = strtolower($m[1]);
            return [
                'target_type' => 'user',
                'normalized_target' => $user,
                'target_hash' => hash('sha256', 'yt:user:' . $user),
            ];
        }

        // Live stream: youtube.com/live/VIDEO_ID
        if (preg_match('#^/live/([\w-]{11})#i', $path, $m)) {
            $vid = $m[1];
            return [
                'target_type' => 'video_id',
                'normalized_target' => $vid,
                'target_hash' => hash('sha256', 'yt:video:' . $vid),
                'is_stream' => true,
            ];
        }

        parse_str($parts['query'] ?? '', $q);
        if (!empty($q['v']) && preg_match('/^[\w-]{11}$/', (string) $q['v'])) {
            $vid = (string) $q['v'];
            return [
                'target_type' => 'video_id',
                'normalized_target' => $vid,
                'target_hash' => hash('sha256', 'yt:video:' . $vid),
            ];
        }

        $norm = strtolower($host . $path);
        return [
            'target_type' => 'url_path',
            'normalized_target' => mb_substr($norm, 0, 500),
            'target_hash' => hash('sha256', 'yt:url:' . $norm),
        ];
    }

    /** Raw link hash for order-level task dedupe (lightweight actions). */
    public static function linkHash(string $link): string
    {
        return hash('sha256', strtolower(trim($link)));
    }
}
