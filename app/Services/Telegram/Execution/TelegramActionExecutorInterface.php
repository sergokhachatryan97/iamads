<?php

namespace App\Services\Telegram\Execution;

use danog\MadelineProto\API;

/**
 * Interface for executing a single Telegram action via MadelineProto.
 * Each executor validates required payload fields and returns a standardized result.
 */
interface TelegramActionExecutorInterface
{
    /**
     * Execute the action.
     *
     * @param API $madeline MadelineProto API instance
     * @param array $payload Task payload (link, link_hash, post_id, per_call, parsed, etc.)
     * @return array{ok: bool, error?: string, state?: string, retry_after?: int, data?: array}
     * @throws \InvalidArgumentException If required payload fields are missing
     */
    public function handle(API $madeline, array $payload): array;
}
