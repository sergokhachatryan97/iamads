<?php

namespace App\Services\Telegram;

use App\Support\TelegramChatType;
use App\Support\TelegramLinkParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramInspector
{
    public function __construct(
        private TelegramMtprotoPoolService $mtprotoPool,
        private TelegramLinkInspector      $telegramLinkInspector,
    ) {}

    /**
     * Inspect a Telegram link.
     * @param bool $forB2c When true, use only mtproto accounts with is_b2c=true (for InspectTelegramLinkJob).
     */
    public function inspect(string $link, ?array $templateKey = [], bool $forB2c = false): array
    {
        $parsed = TelegramLinkParser::parse($link);

        $result = [
            'ok' => false,
            'parsed' => $parsed,
            'chat_type' => null,      // channel | supergroup | group | bot
            'title' => null,
            'member_count' => null,
            'is_paid_join' => false,
            'error_code' => null,
            'error' => null,
            'resolved' => null,

            'audience_type' => null, // subscribers | members
            'is_channel'    => false,
            'is_group'      => false,
        ];

        if (count($templateKey) > 0 && !in_array($parsed['kind'], $templateKey, true)) {
            return $this->fail($result, 'INVALID_FORMAT', 'Invalid Telegram link format');
        }

        if (($parsed['kind'] ?? 'unknown') === 'unknown') {
            return $this->fail($result, 'INVALID_FORMAT', 'Invalid Telegram link format');
        }

        if ($parsed['kind'] === 'special') {
            return $this->fail($result, 'NOT_A_CHAT', 'Link is not a joinable chat');
        }

        /* ===============================
         * BOT START LINK
         * =============================*/
        if (in_array($parsed['kind'], ['bot_start', 'bot_start_with_referral'], true)) {
            $username = $parsed['username'] ?? null;

            if (!$username) {
                return $this->fail($result, 'INVALID_FORMAT', 'Bot username not found in link');
            }

            $telegramLinkInspector = $this->telegramLinkInspector->inspect($link);
            if ($telegramLinkInspector['status'] == 'ambiguous'){
                return $this->fail(
                    $result,
                    'RESOLVE_FAILED',
                    'Bot does not exist'
                );
            }

            if ($telegramLinkInspector['status'] == 'ok'){
                $result['ok'] = true;
                $result['chat_type'] = in_array($telegramLinkInspector['entity_kind'], ['bot_start', 'bot_start_with_referral'], true) ?'bot' : $telegramLinkInspector['entity_kind'];
                $result['is_channel'] = $telegramLinkInspector['entity_kind'] === 'channel' ?? false;
                $result['parsed']['kind'] = $telegramLinkInspector['entity_kind'];
                $result['title'] = $username;
                $result['resolved'] = [
                    'source' => 'parser_only',
                ];

                return $result;
            } else {

                try {
                    $mt = $this->mtprotoPool->resolveIsBotByUsername($username, $forB2c);

                    if (($mt['ok'] ?? false) === true) {
                        // Normalize to same categories we need
                        if (($mt['is_bot'] ?? false) === true) {
                            $type = 'bot';
                            $result['member_count'] =(isset($mt['participants_count']) ? (int)$mt['participants_count'] : null);
                        } else {
                            $t = (string)($mt['type'] ?? 'unknown');
                            // your resolver returns: channel|supergroup|user|unknown...
                            if (in_array($t, ['channel', 'supergroup', 'group'], true)) $type = $t;
                            elseif ($t === 'user') $type = 'user';
                        }
                    }
                } catch (\Throwable $e) {}
            }

            // 3) Decision
            if (in_array($type, ['channel', 'supergroup', 'group'], true)) {
                // Re-route to normal public_username flow
                $parsed['kind'] = 'public_username';
                $result['parsed'] = $parsed;
            } else {
                // If type is bot OR unknown OR user -> treat as bot_start (safe default)
                $result['ok'] = true;
                $result['chat_type'] = 'bot';
                $result['title'] = $username;

                $result['audience_type'] = null;
                $result['is_channel'] = false;
                $result['is_group'] = false;

                $result['resolved'] = [
                    'source' => 'parser_only',
                    'data' => $parsed,
                ];

                return $result;
            }
        }

        /* ===============================
         * PUBLIC USERNAME / PUBLIC POST
         * =============================*/
        if (in_array($parsed['kind'], ['public_username', 'public_post'], true)) {

            $username = $parsed['username'] ?? null;
            if (!$username) {
                return $this->fail($result, 'INVALID_FORMAT', 'Username not found in link');
            }

            $postId = $parsed['post_id'] ?? null;

            if ($postId) {
                $info = $this->mtprotoPool->getAndCheckInfoByPostId($username, $postId, $forB2c);
                if (($info['ok'] ?? false) === true) {
                    $result['ok'] = true;
                    $result['title'] = $username;
                    $result['chat_type'] = 'channel';
                    $result['audience_type'] = null;
                    $result['is_channel'] = true;
                    $result['is_group'] = false;
                    $result['is_poll'] = $info['is_poll'] ?? false;
                    $result['member_count'] = $info['views'] ?? null;

                    return $result;
                }
            } else {

                $telegramLinkInspector = $this->telegramLinkInspector->inspect($link);

                if ($telegramLinkInspector['status'] == 'ambiguous'){
                    return $this->fail(
                        $result,
                        'RESOLVE_FAILED',
                        'Chat or User does not exist'
                    );
                }

                if ($telegramLinkInspector['status'] == 'ok'  && in_array($telegramLinkInspector['entity_kind'], ['bot_start', 'bot_start_with_referral'], true)){
                    $result['ok'] = true;
                    $result['chat_type'] = 'bot';
                    $result['parsed']['kind'] = $telegramLinkInspector['entity_kind'];
                    $result['title'] = $username;

                    return $result;
                }

                $info = $this->mtprotoPool->getInfoByUsername($username, $forB2c);

                if (($info['ok'] ?? false) === true) {

                    $rawChat = $info['raw_chat'] ?? [];
                    $nature = TelegramChatType::natureFromMtprotoChat(is_array($rawChat) ? $rawChat : []);

                    $result['ok'] = true;
                    $result['chat_type'] = $nature['chat_type'] ?? $info['type'] ?? null;
                    $result['audience_type'] = $nature['audience'];
                    $result['is_channel'] = $nature['is_channel'];
                    $result['is_group'] = $nature['is_group'];

                    $result['title'] =
                        ($rawChat['title'] ?? null)
                        ?? ($rawChat['username'] ?? null)
                        ?? ($username);


                    $result['member_count'] =
                        (isset($info['participants_count']) ? (int)$info['participants_count'] : null);

                    // paid messages check (getInfo only)
                    if (in_array($result['chat_type'], ['supergroup', 'channel'], true)) {
                        $maybeFail = $this->ensureNoPaidMessagesOrFail($result, $username, null, $info, $forB2c);
                        if (is_array($maybeFail)) return $maybeFail;
                    }

                    $result['resolved'] = [
                        'source' => 'mtproto_getinfo',
                        'raw' => $info,
                    ];

                    return $result;
                }
            }


            $mtCode  = strtoupper((string)($info['error_code'] ?? ''));

            $mtTemporary = [
                'NO_AVAILABLE_ACCOUNTS',
                'MTPROTO_DEADLINE_EXCEEDED',
                'WORKER_SHUTDOWN',
                'STREAM_CLOSED',
                'IPC_UNAVAILABLE',
                'FLOOD_WAIT',
                'MT_CALL_FAILED',
                'MTPROTO_THROTTLE_SLOT_UNAVAILABLE',
            ];

            if (in_array($mtCode, $mtTemporary, true)) {
                return $this->fail(
                    $result,
                    'RESOLVE_TEMPORARY_UNAVAILABLE',
                    'Unable to resolve chat due to temporary infrastructure issue',
                    [
                        'mtproto' => $info,
                    ]
                );
            }

            return $this->fail(
                $result,
                'RESOLVE_FAILED',
                'Chat or user does not exist',
                [
                    'mtproto' => $info,
                ]
            );

        }

        /* ===============================
         * PUBLIC POST COMMENT
         * =============================*/

        if ($parsed['kind'] === 'public_post_comment_reaction') {

            $username = $parsed['username'] ?? null;

            if (!$username) {
                return $this->fail($result, 'INVALID_FORMAT', 'Username not found in link');
            }

            $postId = $parsed['post_id'] ?? null;
            $commentId = $parsed['comment_id'] ?? null;

            $info = $this->mtprotoPool->validatePostCommentLinkOptimal($username, $postId, $commentId, $forB2c);
            if (($info['ok'] ?? false) === true) {
                $result['ok'] = true;
                $result['title'] = $username;
                $result['chat_type'] = 'channel';
                $result['audience_type'] = null;
                $result['is_channel'] = true;
                $result['is_group'] = false;
                $result['member_count'] = $info['reactions_total'] ?? null;

                return $result;
            }


            $mtCode  = strtoupper((string)($info['error_code'] ?? ''));

            $mtTemporary = [
                'NO_AVAILABLE_ACCOUNTS',
                'MTPROTO_DEADLINE_EXCEEDED',
                'MTPROTO_THROTTLE_SLOT_UNAVAILABLE',
                'WORKER_SHUTDOWN',
                'STREAM_CLOSED',
                'IPC_UNAVAILABLE',
                'FLOOD_WAIT',
                'MT_CALL_FAILED',
            ];

            if (in_array($mtCode, $mtTemporary, true)) {
                return $this->fail(
                    $result,
                    'RESOLVE_TEMPORARY_UNAVAILABLE',
                    'Unable to resolve chat due to temporary infrastructure issue',
                    [
                        'mtproto' => $info,
                    ]
                );
            }

            return $this->fail(
                $result,
                'RESOLVE_FAILED',
                'Chat or user does not exist',
                [
                    'mtproto' => $info,
                ]
            );

        }

        if (in_array($parsed['kind'], ['story_link'], true)) {

            $username = $parsed['username'] ?? null;

            if (!$username) {
                return $this->fail($result, 'INVALID_FORMAT', 'Username not found in link');
            }


            $storyId = (int)($parsed['story_id'] ?? 0);
            if ($storyId <= 0) {
                return $this->fail($result, 'INVALID_FORMAT', 'Story id missing in link');
            }

            $info = $this->mtprotoPool->getInfoByUsername($username, $forB2c);

            if (($info['ok'] ?? false) !== true) {
                return $this->fail(
                    $result,
                    $info['error_code'] ?? 'GETINFO_FAILED',
                    $info['error'] ?? 'Unable to fetch peer info');
            }

// set resolved values from getInfo (keep your existing mapping)
            $rawChat = $info['raw_chat'] ?? [];
            $result['chat_type'] = $info['type'] ?? null;
            $result['title'] = $rawChat['title'] ?? ($rawChat['first_name'] ?? null);
            $result['audience_type'] = $info['audience_type'] ?? null;

            $result['resolved'] = [
                'mtproto' => $info,
            ];

            if (($info['type'] ?? null) !== 'channel') {
                return $this->fail(
                    $result,
                    'NOT_CHANNEL',
                    'Only public channel story links are allowed');
            }

            if (empty($rawChat['username'] ?? null)) {
                return $this->fail(
                    $result,
                    'NOT_PUBLIC_CHANNEL',
                    'Only public/open channel story links are allowed');
            }

            if ((bool)($rawChat['stories_unavailable'] ?? false) === true) {
                return $this->fail(
                    $result,
                    'STORIES_UNAVAILABLE',
                    'Stories are unavailable for this channel with the current account'
                );
            }

// ✅ validate story existence + public + active using FULL INFO (already inside getInfo payload)
            $stories = $info['raw']['full']['stories']['stories'] ?? [];
            if (!is_array($stories) || $stories === []) {
                return $this->fail(
                    $result,
                    'STORY_NOT_IN_FULLINFO',
                    'Story not found in channel full info (may be not active or not accessible)'
                );
            }

            $found = null;
            foreach ($stories as $s) {
                if (($s['_'] ?? null) === 'storyItem' && (int)($s['id'] ?? 0) === $storyId) {
                    $found = $s;
                    break;
                }
            }

            if (!$found) {
                return $this->fail(
                    $result,
                    'STORY_ID_MISMATCH',
                    "Story id={$storyId} not found in channel full info",
                    ['mtproto_getinfo' => $info]
                );
            }

            if (!(bool)($found['public'] ?? false)) {
                return $this->fail(
                    $result,
                    'STORY_NOT_PUBLIC',
                    'Story is not public'
                );
            }

            $expire = (int)($found['expire_date'] ?? 0);
            if ($expire > 0 && $expire <= time()) {
                return $this->fail(
                    $result,
                    'STORY_EXPIRED',
                    'Story has expired'
                );
            }

// ✅ SUCCESS
            $result['ok'] = true;
            $result['parsed']['is_story'] = true;
            $result['parsed']['story_id'] = $storyId;
            $result['resolved']['story'] = $found;
            $result['member_count'] = $stories['views']['views_count'] ?? null;

            return $result;
        }

        /* ===============================
         * INVITE LINK
         * =============================*/
        if (($parsed['kind'] ?? null) === 'invite') {
            $hash = $parsed['hash'] ?? null;

            if (!$hash) {
                return $this->fail($result, 'INVALID_FORMAT', 'Invite hash missing');
            }

//            $telegramLinkInspector = $this->telegramLinkInspector->inspect($link);
//
//            if ($telegramLinkInspector['status'] == 'ambiguous'){
//                return $this->fail(
//                    $result,
//                    'RESOLVE_FAILED',
//                    'Chat or Group does not exist'
//                );
//            }


            $invite = $this->mtprotoPool->checkInvite($hash, $forB2c);

            if (($invite['ok'] ?? false) !== true) {
                return $this->fail(
                    $result,
                    $invite['error_code'] ?? 'INVALID_LINK',
                    $invite['error'] ?? 'Invalid invite link',
                    $invite
                );
            }

            if (!empty($invite['is_paid_join'])) {
                $result['is_paid_join'] = true;

                return $this->fail(
                    $result,
                    'INVALID_LINK',
                    'Paid Telegram groups are not supported',
                    $invite
                );
            }

            $raw = $invite['raw'] ?? $invite;

            $rawChat = $raw['chat'] ?? $raw['channel'] ?? null;

            if (is_array($rawChat)) {
                $nature = TelegramChatType::natureFromMtprotoChat($rawChat);
            } else {
                $nature = $this->inferInvitePeer($invite);
            }

            $result['ok']           = true;
            $result['chat_type']     = $nature['chat_type'];
            $result['audience_type'] = $nature['audience'];
            $result['is_channel']    = (bool) $nature['is_channel'];
            $result['is_group']      = (bool) $nature['is_group'];

            $result['title'] = $invite['title']
                ?? ($raw['title'] ?? null)
                ?? ($rawChat['title'] ?? null);

            $result['member_count'] = $invite['participants_count']
                ?? ($raw['participants_count'] ?? null)
                ?? ($rawChat['participants_count'] ?? null);

            $result['resolved'] = $invite;

            return $result;


        }

        /* ===============================
         * PRIVATE POSTS
         * =============================*/
        if (($parsed['kind'] ?? null) === 'private_post') {
            return $this->fail($result, 'NOT_IMPLEMENTED', 'Private post links are not supported');
        }

        return $this->fail(
            $result,
            'UNKNOWN_KIND',
            'Unknown link type: ' . ($parsed['kind'] ?? 'null')
        );
    }


    private function ensureNoPaidMessagesOrFail(array $result, string $username, mixed $botPayload = null, ?array $mtprotoInfo = null, bool $forB2c = false): ?array
    {
        $username = ltrim(strtolower(trim($username)), '@');

        // 0) CACHE FIRST
        $cacheKey = 'tg:paid_messages:' . $username;
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && array_key_exists('paid', $cached)) {
            if (!empty($cached['paid'])) {
                return $this->fail(
                    $result,
                    'PAID_MESSAGES',
                    'This Telegram channel/supergroup requires paid messages (Stars). Paid messages are not supported.',
                    [
                        'source' => 'cache',
                        'cache' => $cached,
                        'bot_api' => $botPayload,
                    ]
                );
            }
            return null;
        }

        $storeCache = function (bool $paid, int $stars, string $source, array $extra = []) use ($cacheKey) {
            Cache::put($cacheKey, array_merge([
                'paid' => $paid,
                'stars' => $stars,
                'checked_at' => now()->toIso8601String(),
                'source' => $source,
            ], $extra), now()->addHours(6));
        };

        // 1) Ensure we have MTProto getInfo payload
        $info = $mtprotoInfo;
        if (!$info) {
            $info = $this->mtprotoPool->getInfoByUsername($username, forB2c: $forB2c);
        }

        if (($info['ok'] ?? false) === true) {
            $paidStars = $this->extractPaidMessagesStarsFromGetInfo($info);

            if (is_int($paidStars) && $paidStars > 0) {
                $storeCache(true, $paidStars, 'mtproto_getinfo');

                return $this->fail(
                    $result,
                    'PAID_MESSAGES',
                    'This Telegram channel/supergroup requires paid messages (Stars). Paid messages are not supported.',
                    [
                        'source' => 'mtproto_getinfo',
                        'paid_messages_stars' => $paidStars,
                        'bot_api' => $botPayload,
                        'mtproto_info' => $info,
                    ]
                );
            }

            // field exists and 0 => safe
            if ($paidStars !== null) {
                $storeCache(false, (int)$paidStars, 'mtproto_getinfo');
                return null;
            }
        }


        return null;
    }

    private function extractPaidMessagesStarsFromGetInfo(array $mtprotoInfo): ?int
    {
        $raw = $mtprotoInfo['raw'] ?? null;
        if (!is_array($raw)) return null;

        $chat = $raw['Chat'] ?? $raw['chat'] ?? null;
        if (!is_array($chat)) return null;

        $v = $chat['send_paid_messages_stars']
            ?? $chat['paid_messages_price']
            ?? null;

        if (is_numeric($v)) {
            return (int) $v;
        }

        $avail = $chat['paid_messages_available'] ?? null;
        if ($avail === true || $avail === 1 || $avail === '1') return 1;

        return null;
    }

    private function fail(array $result, string $code, string $message, mixed $resolved = null): array
    {
        $result['ok'] = false;
        $result['error_code'] = $code;
        $result['error'] = $message;

        if ($resolved !== null) {
            $result['resolved'] = $resolved;
        }

        return $result;
    }

    private function inferInvitePeer(array $invite): array
    {
        $raw = $invite['raw'] ?? $invite;

        // chatInvite/chatInvitePeek-ի flags-երը հաճախ chat-ի մեջ են
        $chat = (is_array($raw['chat'] ?? null)) ? $raw['chat'] : $raw;

        $isChannel =
            !empty($chat['channel']) ||
            !empty($chat['broadcast']) ||
            (($chat['_'] ?? null) === 'channel');

        if ($isChannel && !empty($chat['megagroup'])) {
            return [
                'chat_type'  => 'supergroup',
                'audience'   => 'members',
                'is_channel' => false,
                'is_group'   => true,
            ];
        }

        if ($isChannel) {
            return [
                'chat_type'  => 'channel',
                'audience'   => 'subscribers',
                'is_channel' => true,
                'is_group'   => false,
            ];
        }

        return [
            'chat_type'  => 'group',
            'audience'   => 'members',
            'is_channel' => false,
            'is_group'   => true,
        ];
    }

}

