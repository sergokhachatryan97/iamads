<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Service Templates
    |--------------------------------------------------------------------------
    |
    | Fixed set of templates that define service behavior. Each template
    | specifies action, policy, allowed link kinds, and peer types.
    | Admin selects a template during service creation - no manual configuration
    | of action/policy to avoid mistakes.
    |
    */

    // ------------------------------------------------------------
    // PREMIUM TELEGRAM SERVICE TYPES
    // ------------------------------------------------------------
//    'premium_daily_subscribe_public_private_group_channel' => [
//        'label' => 'Premium: Daily Public/Private Group/Channel Subscribe',
//        'action' => 'subscribe',
//        'policy_key' => 'default',
//        'allowed_link_kinds' => ['public_username', 'invite'],
//        'allowed_peer_types' => ['channel', 'group'],
//        'requires_duration_days' => true,
//        'requires_start_param' => false,
//        'default_priority' => 60,
//    ],
//
//    'premium_bot_start' => [
//        'label' => 'Premium: Bot Start',
//        'action' => 'bot_start',
//        'policy_key' => 'bot',
//        'allowed_link_kinds' => ['bot_start', 'public_username'],
//        'allowed_peer_types' => ['bot'],
//        'requires_duration_days' => false,
//        'requires_start_param' => false,
//        'default_priority' => 50,
//    ],
//
//    'premium_bot_start_referral' => [
//        'label' => 'Premium: Bot Start With Referral',
//        'action' => 'bot_start',
//        'policy_key' => 'bot',
//        'allowed_link_kinds' => ['bot_start_with_referral', 'bot_start', 'public_username'],
//        'allowed_peer_types' => ['bot'],
//        'requires_duration_days' => false,
//        'requires_start_param' => true,
//        'default_priority' => 50,
//    ],
//
//    'premium_boost' => [
//        'label' => 'Premium: Boost',
//        'action' => 'subscribe',
//        'policy_key' => 'default',
//        'allowed_link_kinds' => ['boost_link'],
//        'allowed_peer_types' => ['channel'],
//        'requires_duration_days' => true,
//        'requires_start_param' => false,
//        'default_priority' => 60,
//    ],

    // ------------------------------------------------------------
    // BOT START
    // ------------------------------------------------------------
    'bot_start' => [
        'label' => 'TG Bot Start',
        'action' => 'bot_start',
        'policy_key' => 'bot',
        'allowed_link_kinds' => ['bot_start', 'public_username'],
        'allowed_peer_types' => ['bot'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    'bot_start_referral' => [
        'label' => 'TG Bot Start With Referral',
        'action' => 'bot_start',
        'policy_key' => 'bot',
        'allowed_link_kinds' => ['bot_start_with_referral', 'bot_start', 'public_username'],
        'allowed_peer_types' => ['bot'],
        'requires_duration_days' => false,
        'requires_start_param' => true,
        'default_priority' => 50,
    ],

    'bot_start_from_search' => [
        'label' => 'TG Bot Start From Search',
        'action' => 'bot_start',
        'policy_key' => 'bot',
        'allowed_link_kinds' => ['bot_start', 'public_username'],
        'allowed_peer_types' => ['bot'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    // ------------------------------------------------------------
    // SUBSCRIBE - PUBLIC CHANNEL (FAST)  -> policy_key=sub_public
    // ------------------------------------------------------------
    'channel_subscribe' => [
        'label' => 'Channel Subscribe (Public/Private, Live time)',
        'action' => 'subscribe',
        'policy_key' => 'default',
        'allowed_link_kinds' => ['public_username', 'invite'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    'channel_subscribe_private_public' => [
        'label' => 'Subscribe Private, Public TG Channel Live Time',
        'action' => 'subscribe',
        'policy_key' => 'default',
        'allowed_link_kinds' => ['public_username', 'invite'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    'real_channel_subscribe_from_search' => [
        'label' => 'Real TG Channel Subscribe From Search Live Time',
        'action' => 'subscribe',
        'policy_key' => 'default',
        'allowed_link_kinds' => ['public_username'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    'channel_subscribe_daily' => [
        'label' => 'Channel Subscribe (Public/Private, Daily)',
        'action' => 'subscribe',
        'policy_key' => 'sub_public',
        'allowed_link_kinds' => ['public_username', 'invite'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => true,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    'subscribe_by_geo_account' => [
        'label' => 'Channel/Group Subscribe by Geo-Based Account',
        'action' => 'subscribe',
        'policy_key' => 'sub_public',
        'allowed_link_kinds' => ['public_username', 'invite'],
        'allowed_peer_types' => ['channel', 'group', 'supergroup'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    'subscribe_daily_by_geo_account' => [
        'label' => 'Channel/Group Subscribe Daily by Geo-Based Account',
        'action' => 'subscribe',
        'policy_key' => 'default',
        'allowed_link_kinds' => ['public_username', 'invite'],
        'allowed_peer_types' => ['channel', 'group', 'supergroup'],
        'requires_duration_days' => true,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    'group_join' => [
        'label' => 'Join To Group',
        'action' => 'subscribe',
        'policy_key' => 'default',
        'allowed_link_kinds' => ['public_username', 'invite'],
        'allowed_peer_types' => ['group', 'supergroup'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    // ------------------------------------------------------------
    // POST VIEWS / REACTIONS / COMMENTS (PUBLIC CHANNEL POSTS)
    // ------------------------------------------------------------
    'channel_post_views' => [
        'label' => 'TG Channel Post Views',
        'action' => 'view',
        'policy_key' => 'views',
        'allowed_link_kinds' => ['public_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    'channel_post_reactions' => [
        'label' => 'TG Channel Post Reactions',
        'action' => 'react',
        'policy_key' => 'reaction',
        'allowed_link_kinds' => ['public_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    'channel_poll' => [
        'label' => 'TG Channel Poll Votes',
        'action' => 'vote',
        'policy_key' => 'vote',
        'allowed_link_kinds' => ['public_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    'channel_post_repost' => [
        'label' => 'TG Channel Post Repost',
        'action' => 'repost',
        'policy_key' => 'repost',
        'allowed_link_kinds' => ['public_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

        'channel_post_comment_reaction' => [
            'label' => 'TG Channel Post Comment Reaction',
            'action' => 'react',
            'policy_key' => 'comment_reaction',
            'allowed_link_kinds' => ['public_post_comment_reaction'],
            'allowed_peer_types' => ['channel'],
            'requires_duration_days' => false,
            'requires_start_param' => false,
            'default_priority' => 50,
        ],

    //    'channel_post_comment' => [
    //        'label' => 'TG Channel Post Comment',
    //        'action' => 'comment',
    //        'policy_key' => 'comment',
    //        'allowed_link_kinds' => ['public_post'],
    //        'allowed_peer_types' => ['channel'],
    //        'requires_duration_days' => false,
    //        'requires_start_param' => false,
    //        'default_priority' => 50,
    //    ],

        'story_repost' => [
            'label' => 'TG Channel Story Repost',
            'action' => 'story_repost',
            'policy_key' => 'story_repost',
            'allowed_link_kinds' => ['story_link'],
            'allowed_peer_types' => ['channel'],
            'requires_duration_days' => false,
            'requires_start_param' => false,
            'default_priority' => 50,
        ],

        'story_like' => [
            'label' => 'TG Channel Story Like',
            'action' => 'story_like',
            'policy_key' => 'story_like',
            'allowed_link_kinds' => ['story_link'],
            'allowed_peer_types' => ['channel'],
            'requires_duration_days' => false,
            'requires_start_param' => false,
            'default_priority' => 50,
        ],

    //    'invite_subscribers_from_other_channel' => [
    //        'label' => 'Invite Subscribers From Other Channel',
    //        'action' => 'invite_subscribers',
    //        'policy_key' => 'invite_subscribers',
    //        'allowed_link_kinds' => ['public_username', 'invite'],
    //        'allowed_peer_types' => ['channel'],
    //        'requires_duration_days' => false,
    //        'requires_start_param' => false,
    //        'default_priority' => 50,
    //    ],

    /**
     * System-managed: MTProto folder placement + timed removal (no performer tasks).
     * Pricing: the service's rate_per_1000 (and client custom/discount pricing) is treated as a flat
     * order price in USD, not ÷1000 (see OrderService + order create UI when hide_quantity is true).
     */
//    'telegram_premium_folder' => [
//        'label' => 'Telegram Premium Folder',
//        'action' => 'folder_add',
//        'policy_key' => 'premium_folder',
//        'allowed_link_kinds' => ['public_username', 'invite'],
//        'allowed_peer_types' => ['channel', 'group', 'supergroup'],
//        'requires_duration_days' => false,
//        'duration_options' => [30],
//        'hide_quantity' => true,
//        'default_quantity' => 1,
//        'display_note' => '500/day',
//        'system_managed' => true,
//        'requires_start_param' => false,
//        'default_priority' => 50,
//    ],

    /*
    |--------------------------------------------------------------------------
    | Premium template registry (meta — not a selectable template_key)
    |--------------------------------------------------------------------------
    */
    'premium_templates' => [
        'premium_boost',
        'premium_bot_start_referral',
        'premium_bot_start',
        'telegram_premium_folder',
        'premium_daily_subscribe_public_private_group_channel',
    ],
];
