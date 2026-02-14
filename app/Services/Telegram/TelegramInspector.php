<?php

namespace App\Services\Telegram;

use App\Support\TelegramChatType;
use App\Support\TelegramLinkParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramInspector
{
    public function __construct(
        private readonly TelegramBotResolver $botResolver,
        private readonly TelegramMtprotoPoolService $mtprotoPool
    ) {}

    public function inspect(string $link): array
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

            $probe = $this->botResolver->getChat($username);

            $type = null;

            if (($probe['ok'] ?? false) === true) {
                $type = $probe['chat']['type'] ?? null;

            } else {

                try {
                    $mt = $this->mtprotoPool->resolveIsBotByUsername($username);

                    if (($mt['ok'] ?? false) === true) {
                        // Normalize to same categories we need
                        if (($mt['is_bot'] ?? false) === true) {
                            $type = 'bot';
                        } else {
                            $t = (string)($mt['type'] ?? 'unknown');
                            // your resolver returns: channel|supergroup|user|unknown...
                            if (in_array($t, ['channel', 'supergroup', 'group'], true)) $type = $t;
                            elseif ($t === 'user') $type = 'user';
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore, we will fall back to parser_only bot
                }
            }

            // 3) Decision
            if (in_array($type, ['channel', 'supergroup', 'group'], true)) {
                // Re-route to normal public_username flow
                $parsed['kind'] = 'public_username';
                $result['parsed'] = $parsed;
                // continue below...
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
                    'probe' => [
                        'bot_api' => [
                            'ok' => (bool)($probe['ok'] ?? false),
                            'error_code' => $probe['error_code'] ?? null,
                        ],
                        'final_type' => $type,
                    ],
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


            $info = $this->mtprotoPool->getInfoByUsername($username);

            if (($info['ok'] ?? false) === true) {

                $rawChat = $info['raw_chat'] ?? [];
                $nature  = TelegramChatType::natureFromMtprotoChat(is_array($rawChat) ? $rawChat : []);

                $result['ok'] = true;
                $result['chat_type']     = $nature['chat_type'];
                $result['audience_type'] = $nature['audience'];
                $result['is_channel']    = $nature['is_channel'];
                $result['is_group']      = $nature['is_group'];

                $result['title'] =
                    ($rawChat['title'] ?? null)
                    ?? ($rawChat['username'] ?? null)
                    ?? ($username);


                $result['member_count'] =
                    (isset($rawChat['participants_count']) ? (int)$rawChat['participants_count'] : null);

                // paid messages check (getInfo only)
                if (in_array($result['chat_type'], ['supergroup', 'channel'], true)) {
                    $maybeFail = $this->ensureNoPaidMessagesOrFail($result, $username, null, $info);
                    if (is_array($maybeFail)) return $maybeFail;
                }

                $result['resolved'] = [
                    'source' => 'mtproto_getinfo',
                    'raw' => $info,
                ];

                return $result;
            }

            $mtCode  = strtoupper((string)($info['error_code'] ?? ''));
            Log::info(sprintf('Telegram mtCode code: %s', $mtCode), ['link' => $link]);

            $mtTemporary = [
                'NO_AVAILABLE_ACCOUNTS',
                'MTPROTO_DEADLINE_EXCEEDED',
                'WORKER_SHUTDOWN',
                'STREAM_CLOSED',
                'IPC_UNAVAILABLE',
                'FLOOD_WAIT'
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
         * INVITE LINK
         * =============================*/
        if (($parsed['kind'] ?? null) === 'invite') {
            $hash = $parsed['hash'] ?? null;

            if (!$hash) {
                return $this->fail($result, 'INVALID_FORMAT', 'Invite hash missing');
            }

            $invite = $this->mtprotoPool->checkInvite($hash);

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

// ✅ title/member_count-ի ճիշտ fallback
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

    /**
     * Bot API returns chat_type directly: channel|supergroup|group.
     * This is best-effort (MTProto is the source of truth).
     */
    private function applyAudienceFromChatType(array &$result): void
    {
        $ct = $result['chat_type'] ?? null;

        if ($ct === 'channel') {
            $result['audience_type'] = 'subscribers';
            $result['is_channel'] = true;
            $result['is_group'] = false;
            return;
        }

        if (in_array($ct, ['group', 'supergroup'], true)) {
            $result['audience_type'] = 'members';
            $result['is_channel'] = false;
            $result['is_group'] = true;
            return;
        }

        // bot / unknown
        $result['audience_type'] = null;
        $result['is_channel'] = false;
        $result['is_group'] = false;
    }


    private function ensureNoPaidMessagesOrFail(array $result, string $username, mixed $botPayload = null, ?array $mtprotoInfo = null): ?array
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
            $info = $this->mtprotoPool->getInfoByUsername($username);
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

