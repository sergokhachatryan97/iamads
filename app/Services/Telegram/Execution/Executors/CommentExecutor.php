<?php

namespace App\Services\Telegram\Execution\Executors;

use App\Services\Telegram\Execution\TelegramActionExecutorInterface;
use danog\MadelineProto\API;

class CommentExecutor implements TelegramActionExecutorInterface
{
    public function handle(API $madeline, array $payload): array
    {
        $parsed = $payload['parsed'] ?? [];
        $postId = $payload['post_id'] ?? $parsed['post_id'] ?? null;
        $username = $parsed['username'] ?? $payload['link'] ?? null;
        $message = $payload['comment_text'] ?? $payload['message'] ?? '';
        if (empty($username)) {
            throw new \InvalidArgumentException('comment requires link or parsed.username in payload');
        }
        if (empty($postId)) {
            throw new \InvalidArgumentException('comment requires post_id in payload when link_kind is post');
        }

        $username = $this->normalizeUsername($username);

        try {
            $info = $madeline->getInfo($username);
            $inputPeer = $this->extractInputPeer($info);
            if (!$inputPeer) {
                return ['ok' => false, 'error' => 'Could not resolve peer', 'state' => 'done'];
            }

            $madeline->messages->sendMessage([
                'peer' => $inputPeer,
                'reply_to' => ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => (int) $postId],
                'message' => $message,
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
