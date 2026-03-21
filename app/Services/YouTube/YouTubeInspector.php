<?php

namespace App\Services\YouTube;

/**
 * Inspects a YouTube URL: parses it, calls YouTube Data API v3 for metadata,
 * and returns a structured result for InspectYouTubeLinkJob.
 *
 * Link type detection is URL-based first (no API). Then videos.list or
 * channels.list (or forHandle / search) is used to verify existence and fetch metadata.
 * Video results are classified via YouTubeResourceClassifier (regular_video, shorts_video,
 * live_stream, upcoming_live_stream, finished_stream_replay). Channel results use
 * link_type: direct_channel, handle_channel, custom_channel_url.
 */
class YouTubeInspector
{
    /** Final link_type values for channels */
    public const LINK_TYPE_DIRECT_CHANNEL = 'direct_channel';
    public const LINK_TYPE_HANDLE_CHANNEL = 'handle_channel';
    public const LINK_TYPE_CUSTOM_CHANNEL_URL = 'custom_channel_url';
    /** Failure link_type values */
    public const LINK_TYPE_INVALID = 'invalid';
    public const LINK_TYPE_UNSUPPORTED = 'unsupported';
    public const LINK_TYPE_NOT_FOUND = 'not_found';

    public function __construct(
        private YouTubeLinkParser $parser,
        private YouTubeDataApiService $api,
        private YouTubeResourceClassifier $classifier
    ) {}

    /**
     * Inspect a YouTube link: parse URL, call API by type, return structured result.
     *
     * @return array{
     *   ok: bool,
     *   exists?: bool,
     *   link_type: string,
     *   entity_kind?: string,
     *   input?: string,
     *   normalized_url?: string,
     *   video_id?: string,
     *   channel_id?: string,
     *   title?: string,
     *   channel_title?: string,
     *   statistics?: array,
     *   parsed?: array,
     *   error_code?: string,
     *   error?: string
     * }
     */
    public function inspect(string $url): array
    {
        $input = trim($url);
        if ($input === '') {
            return $this->fail('EMPTY_LINK', 'YouTube link is empty', $input);
        }

        $parsed = $this->parser->parse($input);
        if (!($parsed['ok'] ?? false)) {
            $code = $parsed['error_code'] ?? 'PARSE_ERROR';
            $linkType = ($code === 'UNSUPPORTED_URL') ? self::LINK_TYPE_UNSUPPORTED : self::LINK_TYPE_INVALID;
            return $this->fail($code, $parsed['error'] ?? 'Could not parse YouTube URL', $input, $parsed, null, $linkType);
        }

        $entityKind = $parsed['entity_kind'] ?? '';

        if ($entityKind === YouTubeLinkParser::ENTITY_VIDEO) {
            return $this->inspectVideo($input, $parsed);
        }

        if ($entityKind === YouTubeLinkParser::ENTITY_CHANNEL) {
            return $this->inspectChannel($input, $parsed);
        }

        return $this->fail('UNSUPPORTED_TYPE', 'Unsupported YouTube link type', $input, $parsed, null, self::LINK_TYPE_UNSUPPORTED);
    }

    private function inspectVideo(string $input, array $parsed): array
    {
        $videoId = $parsed['video_id'] ?? null;
        if (!$videoId) {
            return $this->fail('MISSING_VIDEO_ID', 'Video ID could not be extracted', $input, $parsed, null, self::LINK_TYPE_INVALID);
        }

        if (!$this->api->hasApiKey()) {
            return $this->successVideoNoApi($input, $parsed, $videoId);
        }

        $video = $this->api->getVideo($videoId);
        if (!($video['exists'] ?? false)) {
            return $this->fail(
                $video['error_code'] ?? 'VIDEO_NOT_FOUND',
                $video['error'] ?? 'Video not found',
                $input,
                $parsed,
                $video,
                self::LINK_TYPE_NOT_FOUND
            );
        }

        $linkType = $this->classifier->classifyVideo($parsed, $video);
        $targetHash = hash('sha256', 'yt:video:' . $videoId);
        $result = [
            'ok' => true,
            'exists' => true,
            'input' => $input,
            'entity_kind' => 'video',
            'link_type' => $linkType,
            'url_pattern' => $parsed['url_pattern'] ?? null,
            'video_id' => $videoId,
            'channel_id' => $video['channel_id'] ?? null,
            'title' => $video['title'] ?? null,
            'channel_title' => $video['channel_title'] ?? null,
            'live_broadcast_content' => $video['live_broadcast_content'] ?? null,
            'statistics' => $video['statistics'] ?? [],
            'parsed' => [
                'target_type' => 'video_id',
                'normalized_target' => $videoId,
                'target_hash' => $targetHash,
                'video_id' => $videoId,
                'channel_id' => $video['channel_id'] ?? null,
                "is_comment_link" => $parsed['is_comment_link'] ?? false,
                "comment_id" => $parsed['comment_id'] ?? null,
            ],
        ];
        if (!empty($parsed['is_stream'])) {
            $result['is_stream'] = true;
        }
        return $result;
    }

    private function successVideoNoApi(string $input, array $parsed, string $videoId): array
    {
        $urlPattern = $parsed['url_pattern'] ?? '';
        $linkType = ($urlPattern === YouTubeLinkParser::URL_PATTERN_SHORTS)
            ? YouTubeResourceClassifier::LINK_TYPE_SHORTS_VIDEO
            : YouTubeResourceClassifier::LINK_TYPE_REGULAR_VIDEO;
        $targetHash = hash('sha256', 'yt:video:' . $videoId);
        return [
            'ok' => true,
            'exists' => true,
            'input' => $input,
            'normalized_url' => $parsed['normalized_url'] ?? ('https://www.youtube.com/watch?v=' . $videoId),
            'entity_kind' => 'video',
            'link_type' => $linkType,
            'url_pattern' => $urlPattern ?: null,
            'video_id' => $videoId,
            'channel_id' => null,
            'title' => null,
            'channel_title' => null,
            'live_broadcast_content' => null,
            'statistics' => [],
            'parsed' => [
                'target_type' => 'video_id',
                'normalized_target' => $videoId,
                'target_hash' => $targetHash,
                'video_id' => $videoId,
                'channel_id' => null,
            ],
        ];
    }

    private function inspectChannel(string $input, array $parsed): array
    {
        $channelId = $parsed['channel_id'] ?? null;
        $handle = $parsed['handle'] ?? null;
        $customSlug = $parsed['custom_slug'] ?? null;
        $username = $parsed['username'] ?? null;

        if ($channelId) {
            return $this->inspectChannelById($input, $parsed, $channelId);
        }
        if ($handle) {
            return $this->inspectChannelByHandle($input, $parsed, $handle);
        }

        return $this->fail('MISSING_CHANNEL_IDENTIFIER', 'Channel identifier could not be extracted', $input, $parsed, null, self::LINK_TYPE_INVALID);
    }

    private function inspectChannelById(string $input, array $parsed, string $channelId): array
    {
        if (!$this->api->hasApiKey()) {
            return $this->successChannelNoApi($input, $parsed, $channelId);
        }

        $channel = $this->api->getChannel($channelId);
        if (!($channel['exists'] ?? false)) {
            return $this->fail(
                $channel['error_code'] ?? 'CHANNEL_NOT_FOUND',
                $channel['error'] ?? 'Channel not found',
                $input,
                $parsed,
                $channel,
                self::LINK_TYPE_NOT_FOUND
            );
        }

        $stats = $channel['statistics'] ?? [];
        $targetHash = hash('sha256', 'yt:channel:' . $channelId);
        return [
            'ok' => true,
            'exists' => true,
            'input' => $input,
            'normalized_url' => $parsed['normalized_url'] ?? ('https://www.youtube.com/channel/' . $channelId),
            'entity_kind' => 'channel',
            'link_type' => self::LINK_TYPE_DIRECT_CHANNEL,
            'url_pattern' => $parsed['url_pattern'] ?? null,
            'channel_id' => $channelId,
            'title' => $channel['title'] ?? null,
            'subscriber_count' => $stats['subscribers'] ?? null,
            'statistics' => $stats,
            'parsed' => [
                'target_type' => 'channel_id',
                'normalized_target' => $channelId,
                'target_hash' => $targetHash,
                'video_id' => null,
                'channel_id' => $channelId,
            ],
        ];
    }

    private function inspectChannelByHandle(string $input, array $parsed, string $handle): array
    {
        if (!$this->api->hasApiKey()) {
            return $this->fail('NO_API_KEY', 'Handle resolution requires YouTube API key', $input, $parsed, null, self::LINK_TYPE_INVALID);
        }

        $channel = $this->api->getChannelByHandle($handle);
        if (!($channel['exists'] ?? false)) {
            return $this->fail(
                $channel['error_code'] ?? 'CHANNEL_NOT_FOUND',
                $channel['error'] ?? 'Channel not found for handle',
                $input,
                $parsed,
                $channel,
                self::LINK_TYPE_NOT_FOUND
            );
        }

        $channelId = $channel['channel_id'];
        $stats = $channel['statistics'] ?? [];
        $targetHash = hash('sha256', 'yt:channel:' . $channelId);
        return [
            'ok' => true,
            'exists' => true,
            'input' => $input,
            'normalized_url' => $parsed['normalized_url'] ?? null,
            'entity_kind' => 'channel',
            'link_type' => self::LINK_TYPE_HANDLE_CHANNEL,
            'url_pattern' => $parsed['url_pattern'] ?? null,
            'channel_id' => $channelId,
            'title' => $channel['title'] ?? null,
            'subscriber_count' => $stats['subscribers'] ?? null,
            'statistics' => $stats,
            'parsed' => [
                'target_type' => 'channel_id',
                'normalized_target' => $channelId,
                'target_hash' => $targetHash,
                'video_id' => null,
                'channel_id' => $channelId,
            ],
        ];
    }

    private function inspectChannelBySearch(string $input, array $parsed, string $query): array
    {
        if (!$this->api->hasApiKey()) {
            return $this->fail('NO_API_KEY', 'Custom URL resolution requires YouTube API key', $input, $parsed, null, self::LINK_TYPE_INVALID);
        }

        $channel = $this->api->resolveChannelBySearch($query);
        if (!($channel['exists'] ?? false)) {
            return $this->fail(
                $channel['error_code'] ?? 'CHANNEL_NOT_FOUND',
                $channel['error'] ?? 'Channel not found',
                $input,
                $parsed,
                $channel,
                self::LINK_TYPE_NOT_FOUND
            );
        }

        $channelId = $channel['channel_id'];
        $stats = $channel['statistics'] ?? [];
        $targetHash = hash('sha256', 'yt:channel:' . $channelId);
        return [
            'ok' => true,
            'exists' => true,
            'input' => $input,
            'normalized_url' => $parsed['normalized_url'] ?? null,
            'entity_kind' => 'channel',
            'link_type' => self::LINK_TYPE_CUSTOM_CHANNEL_URL,
            'url_pattern' => $parsed['url_pattern'] ?? null,
            'channel_id' => $channelId,
            'title' => $channel['title'] ?? null,
            'subscriber_count' => $stats['subscribers'] ?? null,
            'statistics' => $stats,
            'parsed' => [
                'target_type' => 'channel_id',
                'normalized_target' => $channelId,
                'target_hash' => $targetHash,
                'video_id' => null,
                'channel_id' => $channelId,
            ],
        ];
    }

    private function successChannelNoApi(string $input, array $parsed, string $channelId): array
    {
        $targetHash = hash('sha256', 'yt:channel:' . $channelId);
        return [
            'ok' => true,
            'exists' => true,
            'input' => $input,
            'normalized_url' => $parsed['normalized_url'] ?? ('https://www.youtube.com/channel/' . $channelId),
            'entity_kind' => 'channel',
            'link_type' => self::LINK_TYPE_DIRECT_CHANNEL,
            'url_pattern' => $parsed['url_pattern'] ?? null,
            'channel_id' => $channelId,
            'title' => null,
            'subscriber_count' => null,
            'statistics' => [],
            'parsed' => [
                'target_type' => 'channel_id',
                'normalized_target' => $channelId,
                'target_hash' => $targetHash,
                'video_id' => null,
                'channel_id' => $channelId,
            ],
        ];
    }

    /**
     * Map detailed link_type (from inspector) to business target_type: video | live | channel.
     * Used by InspectYouTubeLinkJob for allowed_link_kinds and action policy validation.
     */
    public static function linkTypeToTargetType(string $linkType): string
    {
        if (in_array($linkType, [
            YouTubeResourceClassifier::LINK_TYPE_LIVE_STREAM,
            YouTubeResourceClassifier::LINK_TYPE_UPCOMING_LIVE_STREAM,
            YouTubeResourceClassifier::LINK_TYPE_FINISHED_STREAM_REPLAY,
        ], true)) {
            return 'live';
        }
        if (in_array($linkType, [
            self::LINK_TYPE_DIRECT_CHANNEL,
            self::LINK_TYPE_HANDLE_CHANNEL,
            self::LINK_TYPE_CUSTOM_CHANNEL_URL,
        ], true)) {
            return 'channel';
        }
        if (in_array($linkType, [
            YouTubeResourceClassifier::LINK_TYPE_REGULAR_VIDEO,
            YouTubeResourceClassifier::LINK_TYPE_SHORTS_VIDEO,
        ], true)) {
            return 'video';
        }
        return 'video'; // fallback for unknown video-like types
    }

    private function fail(
        string $errorCode,
        string $error,
        string $input,
        ?array $parsed = null,
        ?array $apiPayload = null,
        ?string $linkType = null
    ): array {
        $result = [
            'ok' => false,
            'exists' => false,
            'input' => $input,
            'entity_kind' => 'unknown',
            'link_type' => $linkType ?? self::LINK_TYPE_INVALID,
            'error_code' => $errorCode,
            'error' => $error,
        ];
        if ($parsed !== null) {
            $result['parsed'] = $parsed;
        }
        if ($apiPayload !== null) {
            $result['api_response'] = $apiPayload;
        }
        return $result;
    }
}
