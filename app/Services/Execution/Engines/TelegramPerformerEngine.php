<?php

namespace App\Services\Execution\Engines;

use App\Services\Execution\Contracts\PerformerEngineInterface;
use App\Services\Telegram\TelegramTaskClaimService;
use App\Services\Telegram\TelegramTaskService;

/**
 * Performer engine for Telegram. Wraps existing Telegram claim/report/check/ignore flow.
 * Delegates to TelegramTaskClaimService and TelegramTaskService – no duplication of business logic.
 */
class TelegramPerformerEngine implements PerformerEngineInterface
{
    public function __construct(
        private TelegramTaskClaimService $claimService,
        private TelegramTaskService $taskService
    ) {}

    /**
     * Claim tasks for the performer. Context must include account_identity (phone).
     */
    public function claim(array $context = []): ?array
    {
        $phone = (string) ($context['account_identity'] ?? '');
        if ($phone === '') {
            return null;
        }
        $limit = (int) ($context['limit'] ?? 1);
        $tasks = $this->claimService->claimForPhone($phone, $limit);
        if (empty($tasks)) {
            return null;
        }
        return $tasks[0];
    }

    /**
     * Report task result by task id (TelegramTask ULID).
     */
    public function report(string $taskId, array $result): array
    {
        return $this->taskService->reportTaskResult($taskId, $result);
    }

    /**
     * Check: Telegram API uses order_id; delegates to reportTaskResult with done/ok.
     */
    public function check(string $id, array $context = []): array
    {
        return $this->taskService->reportTaskResult($id, [
            'state' => 'done',
            'ok' => true,
            'error' => null,
            'retry_after' => null,
            'provider_task_id' => null,
            'data' => null,
        ]);
    }

    /**
     * Ignore: Telegram API uses order_id; delegates to reportTaskResult with failed/ok false.
     */
    public function ignore(string $id, array $context = []): array
    {
        return $this->taskService->reportTaskResult($id, [
            'state' => 'failed',
            'ok' => false,
            'error' => null,
            'retry_after' => null,
            'provider_task_id' => null,
            'data' => null,
        ]);
    }
}
