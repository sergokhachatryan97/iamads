<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotResolver
{
    /**
     * Get chat information using Telegram Bot API.
     *
     * @param string $username Username without @
     * @return array{ok: bool, chat?: array{id: int, type: string, title?: string, username?: string}, error_code?: string, error?: string}
     */
    public function getChat(string $username): array
    {
        $username = ltrim($username, '@');
        $botToken = config('services.telegram.bot_token');

        if (empty($botToken)) {
            return [
                'ok' => false,
                'error_code' => 'BOT_TOKEN_NOT_CONFIGURED',
                'error' => 'Telegram bot token is not configured',
            ];
        }

        // ✅ Global cooldown when Telegram says "retry after ..."
        $cooldownKey = 'tg:botapi:cooldown';
        if (Cache::has($cooldownKey)) {
            $retryAt = Cache::get($cooldownKey);
            return [
                'ok' => false,
                'error_code' => 'BOTAPI_COOLDOWN',
                'error' => 'Bot API is rate-limited. Retry later.',
                'retry_at' => $retryAt,
            ];
        }

        // ✅ Cache chat resolution (avoid hammering Bot API)
        $cacheKey = "tg:botapi:getchat:{$username}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $url = "https://api.telegram.org/bot{$botToken}/getChat";

        try {
            $response = Http::timeout(10)->post($url, [
                'chat_id' => '@' . $username,
            ]);

            $data = $response->json();

            if (!is_array($data)) {
                return [
                    'ok' => false,
                    'error_code' => 'INVALID_RESPONSE',
                    'error' => 'Invalid response from Telegram API',
                ];
            }

            if (($data['ok'] ?? false) === true && isset($data['result'])) {
                $chat = $data['result'];

                $result = [
                    'ok' => true,
                    'chat' => [
                        'id' => $chat['id'] ?? 0,
                        'type' => $chat['type'] ?? 'unknown',
                        'title' => $chat['title'] ?? null,
                        'username' => $chat['username'] ?? null,
                    ],
                ];

                // ✅ cache success for 12 hours
                Cache::put($cacheKey, $result, now()->addHours(12));

                return $result;
            }

            // ✅ Handle 429 properly
            $errorCode = (string) ($data['error_code'] ?? 'UNKNOWN_ERROR');
            $errorDescription = (string) ($data['description'] ?? 'Unknown error');

            if ($errorCode === '429') {
                // Telegram usually returns: "Too Many Requests: retry after N"
                $retryAfter = 0;
                if (preg_match('/retry after (\d+)/i', $errorDescription, $m)) {
                    $retryAfter = (int) $m[1];
                }

                // Put a global cooldown a bit longer than retryAfter
                $seconds = max(30, $retryAfter + 5);
                $retryAt = now()->addSeconds($seconds)->toDateTimeString();

                Cache::put($cooldownKey, $retryAt, now()->addSeconds($seconds));

                return [
                    'ok' => false,
                    'error_code' => '429',
                    'error' => $errorDescription,
                    'retry_after' => $retryAfter,
                    'retry_at' => $retryAt,
                ];
            }

            // cache "not found" type errors briefly to avoid hammering
            $result = [
                'ok' => false,
                'error_code' => $errorCode,
                'error' => $errorDescription,
            ];
            Cache::put($cacheKey, $result, now()->addMinutes(10));

            return $result;

        } catch (\Throwable $e) {
            Log::error('Telegram Bot API request failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error_code' => 'REQUEST_FAILED',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get member count for a chat (optional).
     *
     * @param int|string $chatId
     * @return int|null
     */
    public function getMemberCount($chatId): ?int
    {
        $botToken = config('services.telegram.bot_token');

        if (empty($botToken)) {
            return null;
        }

        $url = "https://api.telegram.org/bot{$botToken}/getChatMembersCount";

        try {
            $response = Http::timeout(10)->post($url, [
                'chat_id' => $chatId,
            ]);

            $data = $response->json();

            if (isset($data['ok']) && $data['ok'] === true && isset($data['result'])) {
                return (int) $data['result'];
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to get member count', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }


}
