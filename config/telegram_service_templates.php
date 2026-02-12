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
    // BOT START
    // ------------------------------------------------------------
    'bot_start' => [
        'label' => 'Bot Start',
        'action' => 'bot_start',
        'policy_key' => 'bot',
        'allowed_link_kinds' => ['bot_start'],
        'allowed_peer_types' => ['bot'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    'bot_start_referral' => [
        'label' => 'Bot Start (Referral Required)',
        'action' => 'bot_start',
        'policy_key' => 'bot',
        'allowed_link_kinds' => ['bot_start_with_referral'],
        'allowed_peer_types' => ['bot'],
        'requires_duration_days' => false,
        'requires_start_param' => true,
        'default_priority' => 50,
    ],

    // ------------------------------------------------------------
    // SUBSCRIBE - PUBLIC CHANNEL (FAST)  -> policy_key=sub_public
    // ------------------------------------------------------------
    'channel_subscribe' => [
        'label' => 'Channel Subscribe (Public, Live time)',
        'action' => 'subscribe',
        'policy_key' => 'sub_public',
        'allowed_link_kinds' => ['public_username'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    'channel_subscribe_daily' => [
        'label' => 'Channel Subscribe (Public, Daily)',
        'action' => 'subscribe',
        'policy_key' => 'sub_public',
        'allowed_link_kinds' => ['public_username'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => true,
        'requires_start_param' => false,
        'default_priority' => 60,
    ],

    // ------------------------------------------------------------
    // SUBSCRIBE - PUBLIC GROUP/SUPERGROUP (USERNAME) (FAST)
    // ------------------------------------------------------------
    'group_subscribe_public' => [
        'label' => 'Group Subscribe (Public, Live time)',
        'action' => 'subscribe',
        'policy_key' => 'sub_public',
        'allowed_link_kinds' => ['public_username'],
        'allowed_peer_types' => ['group', 'supergroup'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 55,
    ],

    'group_subscribe_public_daily' => [
        'label' => 'Group Subscribe (Public, Daily)',
        'action' => 'subscribe',
        'policy_key' => 'sub_public',
        'allowed_link_kinds' => ['public_username'],
        'allowed_peer_types' => ['group', 'supergroup'],
        'requires_duration_days' => true,
        'requires_start_param' => false,
        'default_priority' => 55,
    ],

    // ------------------------------------------------------------
    // PRIVATE / INVITE GROUP (SAFE) -> policy_key=sub_private
    // If you prefer invite link to run action=join instead of subscribe,
    // change 'action' => 'join' here (and update execution_policy_map accordingly).
    // ------------------------------------------------------------
    'group_subscribe_invite' => [
        'label' => 'Group Subscribe (Invite / Private, Live time)',
        'action' => 'subscribe',
        'policy_key' => 'sub_private',
        'allowed_link_kinds' => ['invite'],
        'allowed_peer_types' => ['group', 'supergroup'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 40,
    ],

    'group_subscribe_invite_daily' => [
        'label' => 'Group Subscribe (Invite / Private, Daily)',
        'action' => 'subscribe',
        'policy_key' => 'sub_private',
        'allowed_link_kinds' => ['invite'],
        'allowed_peer_types' => ['group', 'supergroup'],
        'requires_duration_days' => true,
        'requires_start_param' => false,
        'default_priority' => 40,
    ],

    // ------------------------------------------------------------
    // OPTIONAL: INVITE JOIN (if you want invite links to always be join)
    // Use this instead of group_subscribe_invite if you decide semantics = join.
    // ------------------------------------------------------------
    'group_join_invite' => [
        'label' => 'Group Join (Invite / Private)',
        'action' => 'join',
        'policy_key' => 'sub_private',
        'allowed_link_kinds' => ['invite'],
        'allowed_peer_types' => ['group', 'supergroup'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 40,
    ],

    // ------------------------------------------------------------
    // POST VIEWS / REACTIONS / COMMENTS (PUBLIC CHANNEL POSTS)
    // ------------------------------------------------------------
    'channel_post_views' => [
        'label' => 'Channel Post Views',
        'action' => 'view',
        'policy_key' => 'views',
        'allowed_link_kinds' => ['public_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    'channel_post_reactions' => [
        'label' => 'Channel Post Reactions',
        'action' => 'react',
        'policy_key' => 'reaction',
        'allowed_link_kinds' => ['public_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    'channel_post_comments' => [
        'label' => 'Channel Post Comments',
        'action' => 'comment',
        'policy_key' => 'comment',
        'allowed_link_kinds' => ['public_post'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],

    // ------------------------------------------------------------
    // GROUP POST REACTIONS (if your parser marks group posts differently)
    // If your inspector returns kind='public_post' for group posts too, you can remove this.
    // Otherwise, adjust allowed_link_kinds to match your parser output.
    // ------------------------------------------------------------
    'group_post_reactions' => [
        'label' => 'Group Post Reactions',
        'action' => 'react',
        'policy_key' => 'reaction',
        'allowed_link_kinds' => ['public_post'], // change if you use a different kind for group posts
        'allowed_peer_types' => ['group', 'supergroup'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 45,
    ],

    // ------------------------------------------------------------
    // STORY REACTIONS (via public username)
    // ------------------------------------------------------------
    'channel_story_reactions' => [
        'label' => 'Channel Story Reactions',
        'action' => 'story_react',
        'policy_key' => 'story',
        'allowed_link_kinds' => ['public_username'],
        'allowed_peer_types' => ['channel'],
        'requires_duration_days' => false,
        'requires_start_param' => false,
        'default_priority' => 50,
    ],
];
