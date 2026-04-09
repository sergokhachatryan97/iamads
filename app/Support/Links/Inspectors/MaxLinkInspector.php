<?php

namespace App\Support\Links\Inspectors;

use App\Support\Links\LinkInspectorInterface;
/**
 * Validates and parses MAX Messenger links.
 *
 * Supported examples:
 *
 *   @max_advent_2025_bot                => max_bot
 *   max_advent_2025_bot                 => max_bot
 *   max.ru/channelname                  => max_channel
 *   https://max.ru/channelname          => max_channel
 *   max.ru/channelname/123              => max_post
 *   max.ru/channelname/AZ0QrbvxKdk      => max_post
 *   max.ru/c/-73050135573940/AZ1YfIS4U8o => REJECTED (private channel, not accessible)
 *   max.ru/botname?start=ref            => max_bot_with_referral
 *   max.ru/some_bot                     => max_bot
 *   max.ru/join/abc123                  => max_invite
 *   maxapp.ru/invite/abc123             => max_invite
 *   web.maxapp.ru/invite/abc123         => max_invite
 *   max.ru/u/f9LHodD0cOLuKwl...        => max_user_profile
 */
class MaxLinkInspector implements LinkInspectorInterface
{
    private const VALID_DOMAINS = [
        'max.ru',
        'www.max.ru',
        'maxapp.ru',
        'www.maxapp.ru',
        'web.maxapp.ru',
    ];

    private const RESERVED_FIRST_SEGMENTS = [
        'invite',
        'join',
        'u',
    ];

    public function inspect(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return $this->invalid(__('home.link_error'));
        }

        // Legacy: https://@handle from older request normalizers
        if (preg_match('#^https?://@([a-zA-Z0-9_]{3,64})$#i', $url, $legacy)) {
            $url = '@'.$legacy[1];
        }

        // @username or bare username (same rules; pasted without @)
        if (preg_match('/^@?([a-zA-Z0-9_]{3,64})$/', $url, $m)) {
            $username = strtolower($m[1]);

            if (! $this->isValidUsername($username)) {
                return $this->invalid(__('Invalid MAX Messenger username.'));
            }

            return $this->valid('max_bot', [
                'raw' => $url,
                'kind' => 'max_bot',
                'username' => $username,
            ]);
        }

        $normalized = $this->normalizeUrl($url);

        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));
        $path = trim((string) (parse_url($normalized, PHP_URL_PATH) ?? ''), '/');
        $query = (string) (parse_url($normalized, PHP_URL_QUERY) ?? '');

        if (! in_array($host, self::VALID_DOMAINS, true)) {
            return $this->invalid(__('Invalid MAX Messenger link format.'));
        }

        if ($path === '') {
            return $this->invalid(__('Invalid MAX Messenger link — no path.'));
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($segment) => $segment !== ''));

        if ($segments === []) {
            return $this->invalid(__('Invalid MAX Messenger link — no path.'));
        }

        $first = strtolower($segments[0]);
        $parsed = ['raw' => $url];

        /**
         * User profile links
         *
         * Example: max.ru/u/f9LHodD0cOLuKwl-GOX0JtwqdMjG6Z14deb0p2IQm8M0g4uE9pxvrS-OZtc
         */
        if (count($segments) === 2 && $first === 'u') {
            $token = $segments[1];

            if (! preg_match('/^[a-zA-Z0-9_\-]{1,255}$/', $token)) {
                return $this->invalid(__('Invalid MAX Messenger user profile link.'));
            }

            return $this->valid('max_user_profile', array_merge($parsed, [
                'kind' => 'max_user_profile',
                'token' => $token,
            ]));
        }

        /**
         * Invite links
         *
         * Supported:
         *   max.ru/join/hash
         *   maxapp.ru/invite/hash
         *   web.maxapp.ru/invite/hash
         *   www.maxapp.ru/invite/hash
         */
        if (count($segments) === 2 && in_array($first, self::RESERVED_FIRST_SEGMENTS, true)) {
            $hash = $segments[1];

            if (! $this->isValidInviteHash($hash)) {
                return $this->invalid(__('Invalid MAX Messenger invite link.'));
            }

            return $this->valid('max_invite', array_merge($parsed, [
                'kind' => 'max_invite',
                'hash' => $hash,
            ]));
        }

        // Reserved keyword without valid hash
        if (in_array($first, self::RESERVED_FIRST_SEGMENTS, true)) {
            return $this->invalid(__('Invalid MAX Messenger invite link.'));
        }

        /**
         * Private channel post URL (peer-based)
         * Example: https://max.ru/c/-73050135573940/AZ1YfIS4U8o
         *
         * These links use internal peer IDs and are not publicly accessible — reject them.
         */
        if ($first === 'c') {
            return $this->invalid(__('Private channel links (max.ru/c/...) are not supported. Please use a public channel link.'));
        }

        // Main username must be valid for all non-invite links
        if (! $this->isValidUsername($first)) {
            return $this->invalid(__('Invalid MAX Messenger username.'));
        }

        parse_str($query, $queryParams);

        /**
         * Bot with referral
         * Example:
         *   max.ru/botname?start=ref
         */
        if (count($segments) === 1 && isset($queryParams['start']) && $queryParams['start'] !== '') {
            return $this->valid('max_bot_with_referral', array_merge($parsed, [
                'kind' => 'max_bot_with_referral',
                'username' => $first,
                'start' => (string) $queryParams['start'],
            ]));
        }

        /**
         * Post link
         * Example:
         *   max.ru/channelname/123
         *   max.ru/channelname/AZ0QrbvxKdk
         *
         * We only accept exactly 2 path segments to avoid false positives.
         */
        if (count($segments) === 2) {
            $postId = $segments[1];

            if (! $this->isValidPostId($postId)) {
                return $this->invalid(__('Invalid MAX Messenger post link.'));
            }

            return $this->valid('max_post', array_merge($parsed, [
                'kind' => 'max_post',
                'username' => $first,
                'post_id' => $postId,
            ]));
        }

        /**
         * Bot link
         * Example:
         *   max.ru/mfc_khabkrai_max_bot
         */
        if (count($segments) === 1 && $this->looksLikeBotUsername($first)) {
            return $this->valid('max_bot', array_merge($parsed, [
                'kind' => 'max_bot',
                'username' => $first,
            ]));
        }

        /**
         * Channel/group link
         * Example:
         *   max.ru/channelname
         */
        if (count($segments) === 1) {
            return $this->valid('max_channel', array_merge($parsed, [
                'kind' => 'max_channel',
                'username' => $first,
            ]));
        }

        return $this->invalid(__('Invalid MAX Messenger link format.'));
    }

    private function normalizeUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return 'https://'.ltrim($url, '/');
    }

    private function valid(string $kind, array $parsed): array
    {
        return [
            'valid' => true,
            'error' => null,
            'kind' => $kind,
            'parsed' => $parsed,
        ];
    }

    private function invalid(string $error): array
    {
        return [
            'valid' => false,
            'error' => $error,
            'kind' => null,
            'parsed' => [],
        ];
    }

    private function isValidUsername(string $username): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_]{3,64}$/', $username);
    }

    private function isValidInviteHash(string $hash): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]{3,255}$/', $hash);
    }

    private function isValidPostId(string $postId): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]{1,255}$/', $postId);
    }

    /**
     * Numeric channel / chat peer id as used in max.ru/c/{id}/… (may be negative).
     */
    private function isValidMaxPeerId(string $peerId): bool
    {
        return (bool) preg_match('/^-?[0-9]{1,20}$/', $peerId);
    }

    private function looksLikeBotUsername(string $username): bool
    {
        return str_ends_with($username, '_bot') || str_ends_with($username, 'bot');
    }

}
