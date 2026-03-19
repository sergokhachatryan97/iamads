<?php

namespace App\Services\Execution\Engines;

use App\Services\Execution\Contracts\PerformerEngineInterface;
use App\Services\YouTube\YouTubeTaskClaimService;
use App\Services\YouTube\YouTubeTaskService;

/**
 * Performer engine for YouTube. Claim returns one task with link and order info; no account identity.
 * Check/ignore use task_id (YouTubeTask ULID).
 */
class YouTubePerformerEngine implements PerformerEngineInterface
{
    public function __construct(
        private YouTubeTaskClaimService $claimService,
        private YouTubeTaskService $taskService
    ) {}

    /**
     * Claim one task. Context must include account_identity (phone); (account_identity, order, link) is unique.
     */
    public function claim(array $context = []): ?array
    {
        $accountIdentity = (string) ($context['account_identity'] ?? '');
        if ($accountIdentity === '') {
            return null;
        }
        return $this->claimService->claim($accountIdentity);
    }

    public function report(string $taskId, array $result): array
    {
        return $this->taskService->reportTaskResult($taskId, $result);
    }

    /**
     * Check: mark task as done by task_id.
     */
    public function check(string $id, array $context = []): array
    {
        return $this->taskService->reportTaskResult($id, [
            'state' => 'done',
            'ok' => true,
            'error' => null,
        ]);
    }

    /**
     * Ignore: mark task as failed by task_id.
     */
    public function ignore(string $id, array $context = []): array
    {
        return $this->taskService->reportTaskResult($id, [
            'state' => 'failed',
            'ok' => false,
            'error' => null,
        ]);
    }
}
