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
        'label' => "YouTube Comment's + Reaction",
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

    'yt_live_comment' => [
        'label' => 'YouTube Live Comment',
        'action' => 'comment',
        'policy_key' => 'comment',
        'allowed_link_kinds' => ['live'],
        'default_priority' => 50,
    ],

    // ---- Combo (video link: subscribe + view + like + optional comment) ----
    'yt_combo_sub_view_like' => [
        'label' => 'YouTube Sub/View/Like',
        'mode' => 'combo',
        'steps' => ['subscribe', 'view', 'react'],
        'allowed_link_kinds' => ['video'],
        'default_priority' => 50,
    ],

    'yt_combo_sub_view_like_comment_random' => [
        'label' => 'YouTube Sub/View/Like/Random Positive Comment',
        'mode' => 'combo',
        'steps' => ['subscribe', 'view', 'react', 'comment_random_positive'],
        'comment_mode' => 'random_positive',
        'allowed_link_kinds' => ['video'],
        'default_priority' => 50,
    ],

    'yt_combo_sub_view_like_comment_custom' => [
        'label' => 'YouTube Sub/View/Like/Custom Comment',
        'mode' => 'combo',
        'steps' => ['subscribe', 'view', 'react', 'comment_custom'],
        'comment_mode' => 'custom',
        'requires_comment' => true,
        'allowed_link_kinds' => ['video'],
        'default_priority' => 50,
    ],
];
