<?php

namespace App\Services\Telegram\Execution\Executors;

use App\Services\Telegram\Execution\TelegramActionExecutorInterface;
use danog\MadelineProto\API;

class StoryReactExecutor implements TelegramActionExecutorInterface
{
    public function handle(API $madeline, array $payload): array
    {
        $username = $payload['parsed']['username'] ?? $payload['link'] ?? null;
        if (empty($username)) {
            throw new \InvalidArgumentException('story_react requires link or parsed.username in payload');
        }

        $username = $this->normalizeUsername($username);
        $reaction = $payload['reaction'] ?? $payload['parsed']['reaction'] ?? '👍';

        try {
            $info = $madeline->getInfo($username);
            $userId = $info['User']['id'] ?? $info['user_id'] ?? null;
            if (!$userId) {
                return ['ok' => false, 'error' => 'Could not resolve user for story', 'state' => 'done'];
            }

            $stories = $madeline->stories->getPeerStories(['peer' => ['_' => 'inputPeerUser', 'user_id' => $userId, 'access_hash' => $info['User']['access_hash'] ?? 0]]);
            $storyId = null;
            if (isset($stories['stories']['stories'][0])) {
                $storyId = $stories['stories']['stories'][0]['id'] ?? null;
            }
            if ($storyId === null) {
                return ['ok' => false, 'error' => 'No story found', 'state' => 'done'];
            }

            $madeline->stories->sendReaction([
                'peer' => ['_' => 'inputPeerUser', 'user_id' => $userId, 'access_hash' => $info['User']['access_hash'] ?? 0],
                'story_id' => $storyId,
                'reaction' => ['_' => 'reactionEmoji', 'emoticon' => $reaction],
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
}
