<?php

namespace App\Services\Telegram\Execution\Executors;

use App\Services\Telegram\Execution\TelegramActionExecutorInterface;
use danog\MadelineProto\API;

class ViewExecutor implements TelegramActionExecutorInterface
{
    public function handle(API $madeline, array $payload): array
    {
        $parsed = $payload['parsed'] ?? [];
        $postId = $payload['post_id'] ?? $parsed['post_id'] ?? null;
        $username = $parsed['username'] ?? $payload['link'] ?? null;
        if (empty($username)) {
            throw new \InvalidArgumentException('view requires link or parsed.username in payload');
        }

        $username = $this->normalizeUsername($username);
        $postId = $postId ? (int) $postId : null;

        try {
            $info = $madeline->getInfo($username);
            $inputPeer = $this->extractInputPeer($info);
            if (!$inputPeer) {
                return ['ok' => false, 'error' => 'Could not resolve peer', 'state' => 'done'];
            }

            $madeline->messages->getMessagesViews([
                'peer' => $inputPeer,
                'id' => $postId ? [$postId] : [],
                'increment' => true,
            ]);
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
        return ltrim(trim($link), '@');
    }

    private function extractInputPeer(array $info): ?array
    {
        $channel = $info['Chat'] ?? $info['channel'] ?? $info;
        if (isset($channel['_']) && ($channel['_'] === 'channel' || $channel['_'] === 'chat')) {
            return ['_' => 'inputPeerChannel', 'channel_id' => $channel['id'], 'access_hash' => $channel['access_hash'] ?? 0];
        }
        if (isset($channel['id'])) {
            return ['_' => 'inputPeerChat', 'chat_id' => $channel['id']];
        }
        return null;
    }
}
