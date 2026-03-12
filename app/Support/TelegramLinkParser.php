<?php

namespace App\Support;


final class TelegramLinkParser
{
    public static function parse(string $raw): array
    {
        $raw = trim($raw);
        $originalRaw = $raw;

        if ($raw === '') {
            return ['kind' => 'unknown', 'raw' => $originalRaw];
        }

        // Raw only numbers (e.g. 2200701710488708) is not a valid Telegram link
        if (ctype_digit($raw)) {
            return ['kind' => 'unknown', 'raw' => $originalRaw];
        }

        // Accept "t.me/..." without schema
        if (preg_match('#^(t\.me/|telegram\.me/)(.+)$#i', $raw, $m)) {
            $raw = 'https://t.me/' . ltrim($m[2], '/');
        }

        if (stripos($raw, 'tg://') === 0) {
            return self::parseTgProtocol($raw, $originalRaw);
        }

        if (preg_match('#^https?://(t\.me|telegram\.me)/(.+)$#i', $raw, $matches)) {
            $pathAndMaybeQuery = $matches[2];

            $parts = explode('?', $pathAndMaybeQuery, 2);
            $path = preg_replace('/#.*/', '', $parts[0]);
            $query = $parts[1] ?? '';

            parse_str($query, $q);

            return self::parseTMePath($path, $q, $originalRaw);
        }

        if (preg_match('#^@?([a-zA-Z0-9_]{3,32})$#', $raw, $m)) {
            $username = strtolower($m[1]);

            if (self::looksLikeBotUsername($username)) {
                return [
                    'kind' => 'bot_start',
                    'raw' => $originalRaw,
                    'username' => $username,
                    'start' => null,
                    'start_key' => null,
                    'has_referrer' => false,
                ];
            }

            return [
                'kind' => 'public_username',
                'raw' => $originalRaw,
                'username' => $username,
            ];
        }

        return ['kind' => 'unknown', 'raw' => $originalRaw];
    }

    private static function parseTgProtocol(string $raw, string $originalRaw): array
    {
        $u = parse_url($raw);
        $host = $u['host'] ?? '';

        $query = [];
        if (!empty($u['query'])) parse_str($u['query'], $query);

        if ($host === 'resolve' && !empty($query['domain'])) {
            $username = strtolower((string) $query['domain']);

            // Story link (tg://resolve?domain=<username>&story=<story_id>)
            if (isset($query['story']) && $query['story'] !== '') {
                $storyId = (int) $query['story'];
                if ($storyId > 0) {
                    return [
                        'kind' => 'story_link',
                        'raw' => $originalRaw,
                        'username' => $username,
                        'story_id' => $storyId,
                    ];
                }
            }

            $startKey = self::firstStartKey($query);
            if ($startKey !== null) {
                $startVal = (string) ($query[$startKey] ?? '');
                $hasRef = $startVal !== '';

                return [
                    'kind' => $hasRef ? 'bot_start_with_referral' : 'bot_start',
                    'raw' => $originalRaw,
                    'username' => $username,
                    'start' => $hasRef ? $startVal : null,
                    'start_key' => $startKey,
                    'has_referrer' => $hasRef,
                ];
            }

            if (self::looksLikeBotUsername($username)) {
                return [
                    'kind' => 'bot_start',
                    'raw' => $originalRaw,
                    'username' => $username,
                    'start' => null,
                    'start_key' => null,
                    'has_referrer' => false,
                ];
            }

            return ['kind' => 'public_username', 'raw' => $originalRaw, 'username' => $username];
        }

        if ($host === 'join' && !empty($query['invite'])) {
            return ['kind' => 'invite', 'raw' => $originalRaw, 'hash' => (string) $query['invite']];
        }

        return ['kind' => 'unknown', 'raw' => $originalRaw];
    }

    private static function parseTMePath(string $path, array $q, string $raw): array
    {
        $path = trim($path, '/');

        // private posts
        if (preg_match('#^c/(\d+)/(\d+)$#', $path, $m)) {
            return [
                'kind' => 'private_post',
                'raw' => $raw,
                'internal_id' => (int) $m[1],
                'post_id' => (int) $m[2],
            ];
        }

        // invite new
        if (preg_match('#^\+([a-zA-Z0-9_-]+)$#', $path, $m)) {
            return ['kind' => 'invite', 'raw' => $raw, 'hash' => (string) $m[1]];
        }

        // invite old
        if (preg_match('#^joinchat/([a-zA-Z0-9_-]+)$#', $path, $m)) {
            return ['kind' => 'invite', 'raw' => $raw, 'hash' => (string) $m[1]];
        }

        // Telegram story link: /{username}/s/{storyId}
        // https://t.me/<username>/s/<story_id>
        if (preg_match('#^([a-zA-Z0-9_]{3,32})/s/(\d+)$#', $path, $m)) {
            return [
                'kind' => 'story_link',
                'raw' => $raw,
                'username' => strtolower($m[1]),
                'story_id' => (int) $m[2],
            ];
        }


// public post (optionally comment link)
        if (preg_match('#^([a-zA-Z0-9_]{3,32})/(\d+)$#', $path, $m)) {
            $username = strtolower($m[1]);
            $postId = (int) $m[2];

            $commentId = null;
            if (isset($q['comment']) && ctype_digit((string) $q['comment'])) {
                $cid = (int) $q['comment'];
                if ($cid > 0) $commentId = $cid;
            }

            if ($commentId !== null) {
                return [
                    'kind' => 'public_post_comment_reaction',
                    'raw' => $raw,
                    'username' => $username,
                    'post_id' => $postId,
                    'comment_id' => $commentId,
                ];
            }

            return [
                'kind' => 'public_post',
                'raw' => $raw,
                'username' => $username,
                'post_id' => $postId,
            ];
        }

        // username (maybe bot start via query)
        if (preg_match('#^([a-zA-Z0-9_]{3,32})$#', $path, $m)) {
            $username = strtolower($m[1]);

            $startKey = self::firstStartKey($q);
            if ($startKey !== null) {
                $startVal = (string) ($q[$startKey] ?? '');
                $hasRef = $startVal !== '';

                return [
                    'kind' => $hasRef ? 'bot_start_with_referral' : 'bot_start',
                    'raw' => $raw,
                    'username' => $username,
                    'start' => $hasRef ? $startVal : null,
                    'start_key' => $startKey,
                    'has_referrer' => $hasRef,
                ];
            }

            if (self::looksLikeBotUsername($username)) {
                return [
                    'kind' => 'bot_start',
                    'raw' => $raw,
                    'username' => $username,
                    'start' => null,
                    'start_key' => null,
                    'has_referrer' => false,
                ];
            }

            return ['kind' => 'public_username', 'raw' => $raw, 'username' => $username];
        }

        return ['kind' => 'unknown', 'raw' => $raw];
    }

    private static function firstStartKey(array $q): ?string
    {
        foreach (['start', 'startgroup', 'startapp', 'startattach'] as $k) {
            if (array_key_exists($k, $q)) return $k;
        }
        return null;
    }

    private static function looksLikeBotUsername(string $username): bool
    {
        return str_ends_with($username, '_bot') || str_ends_with($username, 'bot');
    }
}
