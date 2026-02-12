<?php

namespace App\Services\Telegram\Execution\Executors;

use App\Services\Telegram\Execution\TelegramActionExecutorInterface;
use danog\MadelineProto\API;

class BotStartExecutor implements TelegramActionExecutorInterface
{
    public function handle(API $madeline, array $payload): array
    {
        $link = $payload['link'] ?? $payload['parsed']['raw'] ?? $payload['parsed']['username'] ?? null;
        if (empty($link)) {
            throw new \InvalidArgumentException('bot_start requires link or parsed.* in payload');
        }

        // ✅ extract username from @bot or t.me link
        $username = $this->extractUsername($link, $payload);

        // ✅ resolve start param from all possible places
        $startParam = $this->resolveStartParam($payload, $link);

        try {
            $info = $madeline->getInfo($username);

            // MadelineProto sometimes returns User/user directly or wrapped
            $user = $info['User'] ?? $info['user'] ?? $info;

            $userId = $user['id'] ?? null;
            $accessHash = $user['access_hash'] ?? null;

            if (!$userId || !$accessHash) {
                return [
                    'ok' => false,
                    'error' => 'Could not resolve bot id/access_hash',
                    'debug' => ['username' => $username, 'has_id' => (bool)$userId, 'has_hash' => (bool)$accessHash],
                    'state' => 'done',
                ];
            }

            // ✅ IMPORTANT: startBot expects InputUser for 'bot'
            $bot = [
                '_' => 'inputUser',
                'user_id' => $userId,
                'access_hash' => $accessHash,
            ];

            $madeline->messages->startBot([
                'bot' => $bot,
                'peer' => ['_' => 'inputPeerSelf'],
                'random_id' => random_int(1, PHP_INT_MAX),
                // If null -> pass empty string to be safe
                'start_param' => (string)($startParam ?? ''),
            ]);

        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'state' => 'done',
            ];
        }

        return [
            'ok' => true,
            'state' => 'done',
            'meta' => [
                'username' => $username,
                'start_param' => $startParam,
            ],
        ];
    }

    /**
     * Extract bot username from payload/link safely:
     * supports: @iamads_bot, iamads_bot, https://t.me/iamads_bot?start=xxx
     */
    private function extractUsername(string $link, array $payload): string
    {
        // If parser already gave username, prefer it
        $parsedUsername = $payload['parsed']['username'] ?? null;
        if (!empty($parsedUsername)) {
            return ltrim(trim($parsedUsername), '@');
        }

        $s = trim($link);

        // @bot
        if (str_starts_with($s, '@')) {
            $s = substr($s, 1);
        }

        // t.me/bot or https://t.me/bot
        if (str_contains($s, 't.me/')) {
            $u = parse_url($s);
            $path = trim($u['path'] ?? '', '/');
            $parts = explode('/', $path);
            $s = $parts[0] ?? $s;
        }

        // remove query leftovers
        if (str_contains($s, '?')) {
            $s = strstr($s, '?', true) ?: $s;
        }

        return $s;
    }

    /**
     * Resolve referral param from:
     * - payload['start_param']
     * - payload['parsed']['start'] (your payload has this)
     * - payload['parsed']['start_key'] (optional)
     * - query string in link (?start=...)
     */
    private function resolveStartParam(array $payload, string $link): ?string
    {
        $p1 = $payload['start_param'] ?? null;
        if (!empty($p1)) return (string)$p1;

        $p2 = $payload['parsed']['start'] ?? null;       // ✅ your payload: parsed.start
        if (!empty($p2)) return (string)$p2;

        $p3 = $payload['parsed']['start_key'] ?? null;   // just in case
        if (!empty($p3)) return (string)$p3;

        // from link query (?start=ref)
        if (str_contains($link, '?')) {
            $u = parse_url($link);
            parse_str($u['query'] ?? '', $q);
            if (!empty($q['start'])) return (string)$q['start'];
        }

        return null;
    }
}
