<?php

namespace App\Services\YouTube;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Data API v3 client. Uses videos.list and channels.list.
 * API key from config('youtube.api_key'). Caches responses to reduce quota.
 */
class YouTubeDataApiService
{
    private const BASE_URL = 'https://www.googleapis.com/youtube/v3';

    public function __construct(
        private ?string $apiKey = null
    ) {
        $this->apiKey = $apiKey ?? config('youtube.api_key', '');
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '' && $this->apiKey !== null;
    }

    /**
     * Fetch video metadata by video id. Uses videos.list (snippet, statistics, liveStreamingDetails).
     *
     * @return array{exists: bool, video_id: string, title?: string, channel_id?: string, channel_title?: string, statistics?: array, published_at?: string, live_broadcast_content?: string, live_streaming_details?: array, error_code?: string, error?: string}
     */
    public function getVideo(string $videoId): array
    {
        $cacheTtl = config('youtube.api_cache_ttl_seconds', 3600);
        $cacheKey = 'yt:video:' . $videoId;

        if ($cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        if (!$this->hasApiKey()) {
            return [
                'exists' => false,
                'video_id' => $videoId,
                'error_code' => 'NO_API_KEY',
                'error' => 'YouTube API key is not configured',
            ];
        }

        $response = Http::timeout(15)->get(self::BASE_URL . '/videos', [
            'id' => $videoId,
            'part' => 'snippet,statistics,liveStreamingDetails',
            'key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            $body = $response->json();
            $err = $body['error'] ?? [];
            $message = $err['message'] ?? $response->body();
            if (isset($err['errors'][0]['reason'])) {
                $reason = $err['errors'][0]['reason'];
                if ($reason === 'quotaExceeded') {
                    Log::warning('YouTube API quota exceeded', ['video_id' => $videoId]);
                }
            }
            return [
                'exists' => false,
                'video_id' => $videoId,
                'error_code' => 'API_ERROR',
                'error' => is_string($message) ? $message : json_encode($message),
            ];
        }

        $data = $response->json();
        $items = $data['items'] ?? [];

        if (empty($items)) {
            return [
                'exists' => false,
                'video_id' => $videoId,
                'error_code' => 'NOT_FOUND',
                'error' => 'Video not found',
            ];
        }



        $item = $items[0];
        $snippet = $item['snippet'] ?? [];
        $stats = $item['statistics'] ?? [];
        $liveDetails = $item['liveStreamingDetails'] ?? null;
        $liveBroadcastContent = $snippet['liveBroadcastContent'] ?? 'none';

        $result = [
            'exists' => true,
            'video_id' => $videoId,
            'title' => $snippet['title'] ?? null,
            'channel_id' => $snippet['channelId'] ?? null,
            'channel_title' => $snippet['channelTitle'] ?? null,
            'published_at' => $snippet['publishedAt'] ?? null,
            'live_broadcast_content' => $liveBroadcastContent,
            'live_streaming_details' => $liveDetails ? [
                'actual_start_time' => $liveDetails['actualStartTime'] ?? null,
                'actual_end_time' => $liveDetails['actualEndTime'] ?? null,
                'scheduled_start_time' => $liveDetails['scheduledStartTime'] ?? null,
            ] : null,
            'statistics' => [
                'views' => isset($stats['viewCount']) ? (int) $stats['viewCount'] : null,
                'likes' => isset($stats['likeCount']) ? (int) $stats['likeCount'] : null,
                'comments' => isset($stats['commentCount']) ? (int) $stats['commentCount'] : null,
            ],
        ];

        if ($cacheTtl > 0) {
            Cache::put($cacheKey, $result, $cacheTtl);
        }

        return $result;
    }

    /**
     * Fetch channel metadata by channel id. Uses channels.list (part=snippet,statistics).
     *
     * @return array{exists: bool, channel_id: string, title?: string, statistics?: array, error_code?: string, error?: string}
     */
    public function getChannel(string $channelId): array
    {
        $cacheTtl = config('youtube.api_cache_ttl_seconds', 3600);
        $cacheKey = 'yt:channel:' . $channelId;

        if ($cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        if (!$this->hasApiKey()) {
            return [
                'exists' => false,
                'channel_id' => $channelId,
                'error_code' => 'NO_API_KEY',
                'error' => 'YouTube API key is not configured',
            ];
        }

        $response = Http::timeout(15)->get(self::BASE_URL . '/channels', [
            'id' => $channelId,
            'part' => 'snippet,statistics',
            'key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            $body = $response->json();
            $err = $body['error'] ?? [];
            $message = $err['message'] ?? $response->body();
            return [
                'exists' => false,
                'channel_id' => $channelId,
                'error_code' => 'API_ERROR',
                'error' => is_string($message) ? $message : json_encode($message),
            ];
        }

        $data = $response->json();
        $items = $data['items'] ?? [];

        if (empty($items)) {
            return [
                'exists' => false,
                'channel_id' => $channelId,
                'error_code' => 'NOT_FOUND',
                'error' => 'Channel not found',
            ];
        }

        $item = $items[0];
        $snippet = $item['snippet'] ?? [];
        $stats = $item['statistics'] ?? [];
        $result = [
            'exists' => true,
            'channel_id' => $channelId,
            'title' => $snippet['title'] ?? null,
            'statistics' => [
                'subscribers' => isset($stats['subscriberCount']) ? (int) $stats['subscriberCount'] : null,
                'videos' => isset($stats['videoCount']) ? (int) $stats['videoCount'] : null,
                'views' => isset($stats['viewCount']) ? (int) $stats['viewCount'] : null,
            ],
        ];

        if ($cacheTtl > 0) {
            Cache::put($cacheKey, $result, $cacheTtl);
        }

        return $result;
    }

    /**
     * Resolve channel by handle (@handle). Uses channels.list with forHandle (quota 1).
     *
     * @param string $handle Handle with or without @ (e.g. @GoogleDevelopers or GoogleDevelopers)
     */
    public function getChannelByHandle(string $handle): array
    {
        $handle = trim($handle);
        if ($handle !== '' && $handle[0] !== '@') {
            $handle = '@' . $handle;
        }
        $cacheTtl = config('youtube.api_cache_ttl_seconds', 3600);
        $cacheKey = 'yt:channel_handle:' . strtolower($handle);

        if ($cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        if (!$this->hasApiKey()) {
            return [
                'exists' => false,
                'channel_id' => null,
                'handle' => $handle,
                'error_code' => 'NO_API_KEY',
                'error' => 'YouTube API key is not configured',
            ];
        }

        $response = Http::timeout(15)->get(self::BASE_URL . '/channels', [
            'forHandle' => $handle,
            'part' => 'snippet,statistics',
            'key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            $body = $response->json();
            $err = $body['error'] ?? [];
            $message = $err['message'] ?? $response->body();
            return [
                'exists' => false,
                'channel_id' => null,
                'handle' => $handle,
                'error_code' => 'API_ERROR',
                'error' => is_string($message) ? $message : json_encode($message),
            ];
        }

        $data = $response->json();
        $items = $data['items'] ?? [];

        if (empty($items)) {
            return [
                'exists' => false,
                'channel_id' => null,
                'handle' => $handle,
                'error_code' => 'NOT_FOUND',
                'error' => 'Channel not found for handle',
            ];
        }

        $item = $items[0];
        $channelId = $item['id'] ?? null;
        $snippet = $item['snippet'] ?? [];
        $stats = $item['statistics'] ?? [];
        $result = [
            'exists' => true,
            'channel_id' => $channelId,
            'handle' => $handle,
            'title' => $snippet['title'] ?? null,
            'statistics' => [
                'subscribers' => isset($stats['subscriberCount']) ? (int) $stats['subscriberCount'] : null,
                'videos' => isset($stats['videoCount']) ? (int) $stats['videoCount'] : null,
                'views' => isset($stats['viewCount']) ? (int) $stats['viewCount'] : null,
            ],
        ];

        if ($cacheTtl > 0) {
            Cache::put($cacheKey, $result, $cacheTtl);
        }

        return $result;
    }

    /**
     * Resolve channel by custom URL slug (/c/slug) or legacy username (/user/name).
     * Uses search.list with type=channel (quota cost higher). Use only when necessary.
     *
     * @param string $query Custom slug or username to search for
     */
    public function resolveChannelBySearch(string $query): array
    {
        $cacheTtl = config('youtube.api_cache_ttl_seconds', 3600);
        $cacheKey = 'yt:channel_search:' . md5(strtolower(trim($query)));

        if ($cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        if (!$this->hasApiKey()) {
            return [
                'exists' => false,
                'channel_id' => null,
                'error_code' => 'NO_API_KEY',
                'error' => 'YouTube API key is not configured',
            ];
        }

        $response = Http::timeout(15)->get(self::BASE_URL . '/search', [
            'q' => $query,
            'type' => 'channel',
            'part' => 'snippet',
            'maxResults' => 1,
            'key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            $body = $response->json();
            $err = $body['error'] ?? [];
            $message = $err['message'] ?? $response->body();
            return [
                'exists' => false,
                'channel_id' => null,
                'error_code' => 'API_ERROR',
                'error' => is_string($message) ? $message : json_encode($message),
            ];
        }

        $data = $response->json();
        $items = $data['items'] ?? [];

        if (empty($items)) {
            return [
                'exists' => false,
                'channel_id' => null,
                'error_code' => 'NOT_FOUND',
                'error' => 'Channel not found for query',
            ];
        }

        $item = $items[0];
        $channelId = $item['snippet']['channelId'] ?? $item['id']['channelId'] ?? null;
        if (!$channelId && isset($item['id']['channelId'])) {
            $channelId = $item['id']['channelId'];
        }

        if (!$channelId) {
            return [
                'exists' => false,
                'channel_id' => null,
                'error_code' => 'INVALID_RESPONSE',
                'error' => 'No channel id in search result',
            ];
        }

        $channelResult = $this->getChannel($channelId);
        $channelResult['resolved_from_search'] = true;
        $channelResult['search_query'] = $query;

        if ($cacheTtl > 0) {
            Cache::put($cacheKey, $channelResult, $cacheTtl);
        }

        return $channelResult;
    }
}
