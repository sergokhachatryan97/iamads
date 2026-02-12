<?php

namespace App\Services\Telegram\Execution\Executors;

use App\Services\Telegram\Execution\TelegramActionExecutorInterface;
use danog\MadelineProto\API;

class JoinExecutor implements TelegramActionExecutorInterface
{
    public function handle(API $madeline, array $payload): array
    {
        $link = $payload['link'] ?? null;
        $parsed = $payload['parsed'] ?? [];
        $inviteHash = $parsed['invite_hash'] ?? null;
        if (empty($inviteHash) && empty($link)) {
            throw new \InvalidArgumentException('join requires link or parsed.invite_hash in payload');
        }

        $hash = $inviteHash ?? $this->extractInviteHashFromLink($link);

        try {
            $madeline->messages->importChatInvite(['hash' => $hash]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'state' => 'done',
            ];
        }

        return ['ok' => true, 'state' => 'done'];
    }

    private function extractInviteHashFromLink(?string $link): string
    {
        if (empty($link)) {
            throw new \InvalidArgumentException('join requires invite hash');
        }
        if (preg_match('#t\.me/\+?([a-zA-Z0-9_-]+)#', $link, $m)) {
            return $m[1];
        }
        return trim(parse_url($link, PHP_URL_PATH) ?? '', '/');
    }
}
