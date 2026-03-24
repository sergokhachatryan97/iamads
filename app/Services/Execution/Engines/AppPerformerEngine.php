<?php

namespace App\Services\Execution\Engines;

use App\Services\App\AppTaskClaimService;
use App\Services\App\AppTaskService;
use App\Services\Execution\Contracts\PerformerEngineInterface;

/**
 * Performer engine for App (App Store / Google Play).
 * Claim returns one task with link and order info; check/ignore use task_id.
 */
class AppPerformerEngine implements PerformerEngineInterface
{
    public function __construct(
        private AppTaskClaimService $claimService,
        private AppTaskService $taskService
    ) {}

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

    public function check(string $id, array $context = []): array
    {
        return $this->taskService->reportTaskResult($id, [
            'state' => 'done',
            'ok' => true,
            'error' => null,
        ]);
    }

    public function ignore(string $id, array $context = []): array
    {
        return $this->taskService->reportTaskResult($id, [
            'state' => 'failed',
            'ok' => false,
            'error' => null,
        ]);
    }
}
