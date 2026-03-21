<?php

namespace App\Services\YouTube;

/**
 * Classifies a YouTube video resource into a detailed link_type using
 * parser output (url_pattern) and API response (live_broadcast_content, live_streaming_details).
 */
class YouTubeResourceClassifier
{
    public const LINK_TYPE_REGULAR_VIDEO = 'regular_video';
    public const LINK_TYPE_SHORTS_VIDEO = 'shorts_video';
    public const LINK_TYPE_LIVE_STREAM = 'live_stream';
    public const LINK_TYPE_UPCOMING_LIVE_STREAM = 'upcoming_live_stream';
    public const LINK_TYPE_FINISHED_STREAM_REPLAY = 'finished_stream_replay';

    /**
     * Classify video into final link_type.
     *
     * @param array $parsed Parser result: url_pattern, is_stream, etc.
     * @param array $video  API getVideo result: live_broadcast_content, live_streaming_details, etc.
     * @return string One of: regular_video, shorts_video, live_stream, upcoming_live_stream, finished_stream_replay
     */
    public function classifyVideo(array $parsed, array $video): string
    {
        $urlPattern = $parsed['url_pattern'] ?? '';
        $liveBroadcastContent = $video['live_broadcast_content'] ?? 'none';
        $liveDetails = $video['live_streaming_details'] ?? null;

        // 1) Live right now
        if ($liveBroadcastContent === 'live') {
            return self::LINK_TYPE_LIVE_STREAM;
        }

        // 2) Scheduled / upcoming
        if ($liveBroadcastContent === 'upcoming') {
            return self::LINK_TYPE_UPCOMING_LIVE_STREAM;
        }


        // 4) Shorts: URL pattern is /shorts/...
        if ($urlPattern === YouTubeLinkParser::URL_PATTERN_SHORTS) {
            return self::LINK_TYPE_SHORTS_VIDEO;
        }

        // 5) Default: regular video
        return self::LINK_TYPE_REGULAR_VIDEO;
    }
}
