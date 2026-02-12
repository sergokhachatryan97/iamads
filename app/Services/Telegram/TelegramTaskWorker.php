<?php

namespace App\Services\Telegram;

use App\Models\TelegramTask;
use App\Services\Telegram\Execution\TelegramExecutionEngine;
use danog\MadelineProto\RPCErrorException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Local worker that leases tasks from DB and executes them via MadelineProto.
 * Uses per-account Redis lock to prevent concurrent Madeline usage.
 */
class TelegramTaskWorker
{
    public function __construct(
        private TelegramTaskService $taskService,
        private TelegramExecutionEngine $executionEngine,
        private LocalMtprotoAccountResolver $mtprotoResolver,
        private MtprotoClientFactory $madelineFactory
    ) {}

    /**
     * Run one batch: lease tasks, execute each, finalize via reportTaskResult.
     *
     * @param int $limit Max tasks to lease
     * @param int|null $leaseTtlSeconds Lease TTL
     * @return array{leased: int, ok: int, failed: int, skipped: int}
     */
    public function runBatch(int $limit = 200, ?int $leaseTtlSeconds = null): array
    {
        $leased = $this->taskService->leaseTasksForLocalWorker($limit, $leaseTtlSeconds);
        $leasedCount = $leased->count();

        Log::info('Local worker lease batch', [
            'batch_size' => $leasedCount,
            'lease_ttl' => $leaseTtlSeconds ?? config('telegram.local_worker.lease_ttl_seconds'),
        ]);

        $ok = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($leased as $task) {
            $result = $this->processTask($task);
            if ($result === 'ok') {
                $ok++;
            } elseif ($result === 'failed') {
                $failed++;
            } else {
                $skipped++;
            }
        }

        return [
            'leased' => $leasedCount,
            'ok' => $ok,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    /**
     * Process a single leased task.
     *
     * @return string 'ok' | 'failed' | 'skipped'
     */
    public function processTask(TelegramTask $task): string
    {
        if ($task->isFinalized()) {
            Log::debug('Local worker skip finalized task', ['task_id' => $task->id]);
            return 'skipped';
        }

        $telegramAccount = $task->telegramAccount;
        if (!$telegramAccount) {
            Log::warning('Local worker skip task: telegram account not found', [
                'task_id' => $task->id,
                'telegram_account_id' => $task->telegram_account_id,
            ]);
            $this->reportFailure($task, 'Telegram account not found');
            return 'failed';
        }

        $accountId = $telegramAccount->id;
        $lockKey = "mtp:lock:{$accountId}";
        $lockTtl = (int) config('telegram.local_worker.per_account_lock_ttl_seconds', 120);
        $lock = Cache::lock($lockKey, $lockTtl);

        if (!$lock->block(1)) {
            Log::debug('Local worker skip task: account lock busy', [
                'task_id' => $task->id,
                'telegram_account_id' => $accountId,
            ]);
            return 'skipped';
        }

        try {
            $mtprotoAccount = $this->mtprotoResolver->resolve($telegramAccount);

            if (!$mtprotoAccount) {
                Log::warning('Local worker skip task: Mtproto account not resolved', [
                    'task_id' => $task->id,
                    'telegram_account_id' => $accountId,
                ]);
                $this->reportFailure($task, 'Mtproto account not found for this Telegram account (phone match)');
                return 'failed';
            }

            Log::info('Local worker task execution start', [
                'task_id' => $task->id,
                'order_id' => $task->order_id,
                'telegram_account_id' => $accountId,
                'action' => $task->action,
            ]);

            $madeline = $this->madelineFactory->makeForRuntime($mtprotoAccount);
            $payload = $task->payload ?? [];
            $payload['link_hash'] = $task->link_hash;

            $result = $this->executionEngine->execute($task->action, $madeline, $payload);

            $reportPayload = [
                'state' => $result['state'] ?? 'done',
                'ok' => (bool) ($result['ok'] ?? false),
                'error' => $result['error'] ?? null,
                'retry_after' => $result['retry_after'] ?? null,
                'data' => $result['data'] ?? null,
            ];

            $this->taskService->reportTaskResult($task->id, $reportPayload);

            if ($reportPayload['ok']) {
                Log::info('Local worker task execution end', [
                    'task_id' => $task->id,
                    'order_id' => $task->order_id,
                    'telegram_account_id' => $accountId,
                    'action' => $task->action,
                    'result' => 'ok',
                ]);
                return 'ok';
            }

            Log::warning('Local worker task execution end (fail)', [
                'task_id' => $task->id,
                'order_id' => $task->order_id,
                'telegram_account_id' => $accountId,
                'action' => $task->action,
                'error' => $reportPayload['error'],
            ]);
            return 'failed';

        } catch (RPCErrorException $e) {
            $this->madelineFactory->forgetRuntimeInstance($mtprotoAccount ?? null);
            $this->handleTaskException($task, $telegramAccount, $e);
            return 'failed';
        } catch (\Throwable $e) {
            if (isset($mtprotoAccount)) {
                $this->madelineFactory->forgetRuntimeInstance($mtprotoAccount);
            }
            $this->handleTaskException($task, $telegramAccount, $e);
            return 'failed';
        } finally {
            $lock->release();
        }
    }

    private function handleTaskException(TelegramTask $task, $telegramAccount, \Throwable $e): void
    {
        Log::error('Local worker task execution error', [
            'task_id' => $task->id,
            'order_id' => $task->order_id,
            'telegram_account_id' => $telegramAccount->id ?? null,
            'action' => $task->action,
            'error' => $e->getMessage(),
        ]);

        $maxAttempts = (int) config('telegram.local_worker.max_attempts', 5);
        $attempt = $task->attempt + 1;
        $task->increment('attempt');
        $task->update([
            'result' => array_merge($task->result ?? [], ['error' => $e->getMessage(), 'attempt' => $attempt]),
        ]);

        $this->taskService->reportTaskResult($task->id, [
            'state' => 'done',
            'ok' => false,
            'error' => $e->getMessage(),
        ]);

        if ($attempt >= $maxAttempts) {
            $task->update(['status' => TelegramTask::STATUS_FAILED]);
        }
    }

    private function reportFailure(TelegramTask $task, string $error): void
    {
        $this->taskService->reportTaskResult($task->id, [
            'state' => 'done',
            'ok' => false,
            'error' => $error,
        ]);
    }
}
