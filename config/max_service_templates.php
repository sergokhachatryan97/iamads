<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MAX Messenger Service Templates
    |--------------------------------------------------------------------------
    |
    | Templates for MAX Messenger services. Each template defines service
    | behavior: action, allowed link kinds, and duration/guarantee options.
    | Admin selects a template during service creation.
    |
    | Link kinds for MAX:
    |   - max_channel    : public channel link (max.ru/channelname)
    |   - max_invite     : private invite link (maxapp.ru/invite/...)
    |   - max_post       : post link (max.ru/channel/post_id or max.ru/c/peerId/token)
    |   - max_bot        : bot link (max.ru/botname)
    |
    | Peer types for MAX:
    |   - channel, group, bot
    |
    */

    // ------------------------------------------------------------
    // SUBSCRIBERS (with guarantee)
    // ------------------------------------------------------------
    'max_subscribers_daily' => [
        'label' => 'Подписчики MAX + гарантия  дней',
        'action' => 'subscribe',
        'policy_key' => 'max_subscribe',
        'allowed_link_kinds' => ['max_channel', 'max_invite'],
        'allowed_peer_types' => ['channel', 'group'],
        'requires_duration_days' => true,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    // ------------------------------------------------------------
    // LIFETIME SUBSCRIBERS (no duration, public + private)
    // ------------------------------------------------------------
    'max_subscribers_lifetime' => [
        'label' => 'Лайфтайм Подписчики MAX без списаний | Для публичного и приватного канала',
        'action' => 'subscribe',
        'policy_key' => 'max_subscribe_lifetime',
        'allowed_link_kinds' => ['max_channel', 'max_invite'],
        'allowed_peer_types' => ['channel', 'group'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    // ------------------------------------------------------------
    // POST VIEWS
    // ------------------------------------------------------------
    'max_post_views' => [
        'label' => 'Просмотры на пост MAX',
        'action' => 'view',
        'policy_key' => 'max_views',
        'allowed_link_kinds' => ['max_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    // ------------------------------------------------------------
    // REPOSTS
    // ------------------------------------------------------------
    'max_repost' => [
        'label' => 'Макс Репосты',
        'action' => 'repost',
        'policy_key' => 'max_repost',
        'allowed_link_kinds' => ['max_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    // ------------------------------------------------------------
    // REACTIONS
    // ------------------------------------------------------------
    'max_reactions' => [
        'label' => 'MAX Реакции',
        'action' => 'react',
        'policy_key' => 'max_reaction',
        'allowed_link_kinds' => ['max_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    // ------------------------------------------------------------
    // BOT START
    // ------------------------------------------------------------
    'max_bot_start_referral' => [
        'label' => 'Бот Старт MAX + Referral',
        'action' => 'bot_start',
        'policy_key' => 'max_bot',
        'allowed_link_kinds' => ['max_bot', 'max_bot_with_referral'],
        'allowed_peer_types' => ['bot'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],
];
