<?php

return [
    /*
    |--------------------------------------------------------------------------
    | YouTube Service Templates
    |--------------------------------------------------------------------------
    |
    | Templates define action and allowed target types (video, live, channel).
    | Must match config('youtube.allowed_actions'): only actions allowed for
    | that target type are valid. InspectYouTubeLinkJob validates link -> target_type
    | and action vs YouTubePolicy.
    |
    | allowed_link_kinds: target types this template accepts (video | live | channel).
    */

    // ---- Channel (subscribe only) ----
    'yt_subscribe' => [
        'label' => 'YouTube Channel Subscribe',
        'action' => 'subscribe',
        'policy_key' => 'subscribe',
        'allowed_link_kinds' => ['channel'],
        'default_priority' => 50,
    ],

    // ---- Video (view, react, comment, share) ----
    'yt_view' => [
        'label' => 'YouTube Video View',
        'action' => 'view',
        'policy_key' => 'view',
        'allowed_link_kinds' => ['video'],
        'default_priority' => 50,
    ],

    'yt_react' => [
        'label' => 'YouTube Video Reaction',
        'action' => 'react',
        'policy_key' => 'react',
        'allowed_link_kinds' => ['video'],
        'default_priority' => 50,
    ],

    'yt_comment' => [
        'label' => 'YouTube Video Comment',
        'action' => 'comment',
        'policy_key' => 'comment',
        'allowed_link_kinds' => ['video'],
        'default_priority' => 50,
    ],

    'yt_comment_react' => [
        'label' => 'YouTube Comment + Reaction',
        'action' => 'comment-react',
        'policy_key' => 'comment_react',
        'allowed_link_kinds' => ['video'],
        'default_priority' => 50,
    ],

    'yt_share' => [
        'label' => 'YouTube Video Share',
        'action' => 'share',
        'policy_key' => 'share',
        'allowed_link_kinds' => ['video'],
        'default_priority' => 50,
    ],

    'yt_watch_time' => [
        'label' => 'YouTube Video Watch Time',
        'action' => 'watch',
        'policy_key' => 'watch',
        'allowed_link_kinds' => ['video'],
        'default_priority' => 50,
        'requires_watch_time' => true,
        'default_watch_time_seconds' => 30,
    ],

    // ---- Live (view, react, comment-react, comment) ----
    'yt_live_view' => [
        'label' => 'YouTube Live View',
        'action' => 'view',
        'policy_key' => 'view',
        'allowed_link_kinds' => ['live'],
        'default_priority' => 50,
    ],

    'yt_live_react' => [
        'label' => 'YouTube Live Reaction',
        'action' => 'react',
        'policy_key' => 'react',
        'allowed_link_kinds' => ['live'],
        'default_priority' => 50,
    ],

    'yt_live_comment_react' => [
        'label' => 'YouTube Live Comment + Reaction',
        'action' => 'comment-react',
        'policy_key' => 'comment_react',
        'allowed_link_kinds' => ['live'],
        'default_priority' => 50,
    ],

    'yt_live_comment' => [
        'label' => 'YouTube Live Comment',
        'action' => 'comment',
        'policy_key' => 'comment',
        'allowed_link_kinds' => ['live'],
        'default_priority' => 50,
    ],
];
