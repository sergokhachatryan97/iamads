<?php

namespace App\Services\Telegram\Execution\Executors;

use App\Services\Telegram\Execution\TelegramActionExecutorInterface;
use danog\MadelineProto\API;

class SubscribeExecutor implements TelegramActionExecutorInterface
{
    public function handle(API $madeline, array $payload): array
    {
        $link = $payload['link'] ?? null;
        $parsed = $payload['parsed'] ?? [];
        if (empty($link) && empty($parsed['username']) && empty($parsed['invite_hash'])) {
            throw new \InvalidArgumentException('subscribe requires link or parsed.username/invite_hash in payload');
        }

        try {
            if (!empty($parsed['invite_hash'])) {
                $madeline->messages->importChatInvite(['hash' => $parsed['invite_hash']]);
            } else {
                $username = $this->normalizeUsername($parsed['username'] ?? $link);
                $info = $madeline->getInfo($username);
                $inputChannel = $this->extractInputChannel($info);
                if ($inputChannel) {
                    $madeline->channels->joinChannel(['channel' => $inputChannel]);
                } else {
                    $madeline->messages->importChatInvite(['hash' => ltrim($username, '-')]);
                }
            }
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'state' => 'done',
            ];
        }

        return ['ok' => true, 'state' => 'done'];
    }

    private function normalizeUsername(string $link): string
    {
        $link = trim($link);
        return ltrim($link, '@');
    }

    private function extractInputChannel(array $info): ?array
    {
        $channel = $info['Chat'] ?? $info['channel'] ?? $info;
        if (isset($channel['_']) && in_array($channel['_'], ['channel', 'chat'], true)) {
            return ['_' => 'inputChannel', 'channel_id' => $channel['id'], 'access_hash' => $channel['access_hash'] ?? 0];
        }
        return null;
    }
}
