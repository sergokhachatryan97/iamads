<?php

namespace App\Jobs;

use App\Models\MtprotoAccountTask;
use App\Models\MtprotoTelegramAccount;
use App\Services\Telegram\AccountSetupTaskExecutor;
use App\Services\Telegram\MtprotoClientFactory;
use danog\MadelineProto\RPCErrorException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runner job that executes a single account setup task.
 *
 * Uses per-account lock (tg:mtproto:lock:{account_id}) and reuses
 * existing error handling patterns from TelegramMtprotoPoolService.
 */
class RunAccountSetupTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // We handle retries via task retry_at, not job retries
    public array $backoff = [];

    public function __construct(public int $taskId) {}

    public function handle(
        AccountSetupTaskExecutor $executor,
        MtprotoClientFactory $factory
    ): void {
        $task = MtprotoAccountTask::query()->with('account')->find($this->taskId);

        if (!$task) {
            Log::warning('Account setup task not found', ['task_id' => $this->taskId]);
            return;
        }

        // Check if task is final
        if ($task->isFinal()) {
            Log::debug('Account setup task already final', [
                'task_id' => $this->taskId,
                'status' => $task->status,
            ]);
            return;
        }

        // Check if task is eligible to run
        if (!$task->isEligibleToRun()) {
            if ($task->retry_at && $task->retry_at->isFuture()) {
                // Reschedule for later
                $delay = max(1, (int) now()->diffInSeconds($task->retry_at, false));
                $this->release($delay);
            }
            return;
        }

        $account = $task->account;

        if (!$account) {
            Log::warning('Account not found for setup task', ['task_id' => $this->taskId]);
            return;
        }

        // Check if account is available
        if (!$account->isAvailable()) {
            Log::debug('Account not available for setup task', [
                'task_id' => $this->taskId,
                'account_id' => $account->id,
            ]);
            // Reschedule task for later
            $this->release(300); // 5 minutes
            return;
        }

        // Acquire per-account lock (same key as pool service)
        $lockKey = "tg:mtproto:lock:{$account->id}";
        $jobTimeout = (int) config('telegram_mtproto.job_timeout_seconds', 60);
        $buffer = (int) config('telegram_mtproto.lock_ttl_buffer_seconds', 60);
        $ttlCfg = (int) config('telegram_mtproto.account_lock_ttl_seconds', 0);
        $lockTtl = $ttlCfg > 0 ? $ttlCfg : ($jobTimeout + $buffer);

        $lock = Cache::lock($lockKey, $lockTtl);

        if (!$lock->block(1)) {
            Log::debug('MTP_LOCK_BUSY for setup task', [
                'task_id' => $this->taskId,
                'account_id' => $account->id,
            ]);
            $this->release(random_int(5, 15));
            return;
        }

        try {
            // Mark task as running
            $task->update(['status' => MtprotoAccountTask::STATUS_RUNNING]);

            Log::info('TASK_START', [
                'task_id' => $this->taskId,
                'account_id' => $account->id,
                'task_type' => $task->task_type,
            ]);

            // Create Madeline instance
            $madeline = $factory->makeForRuntime($account);

            // Execute task
            $result = $executor->execute($task->task_type, $madeline, $account, $task->payload_json ?? []);

            if ($result['ok'] ?? false) {
                // Success
                $task->update([
                    'status' => MtprotoAccountTask::STATUS_DONE,
                    'last_error_code' => null,
                    'last_error' => null,
                ]);

                $account->recordSuccess();

                Log::info('TASK_OK', [
                    'task_id' => $this->taskId,
                    'account_id' => $account->id,
                    'task_type' => $task->task_type,
                ]);
            } else {
                // Task failed (non-RPC error)
                $errorCode = (string) ($result['error_code'] ?? 'TASK_FAILED');
                $error = (string) ($result['error'] ?? 'Task execution failed');

                $this->handleTaskFailure($task, $account, $errorCode, $error, null);
            }

        } catch (RPCErrorException $e) {
            // Handle RPC errors using existing pattern
            $factory->forgetRuntimeInstance($account);
            $handled = $this->handleRpcError($account, $e);

            $errorCode = $handled['error_code'] ?? 'MTPROTO_ERROR';
            $error = $e->getMessage();

            $this->handleTaskFailure($task, $account, $errorCode, $error, $handled);

        } catch (\Throwable $e) {
            // Generic error
            $factory->forgetRuntimeInstance($account);

            $errorCode = 'UNKNOWN_ERROR';
            $error = $e->getMessage();

            $this->handleTaskFailure($task, $account, $errorCode, $error, null);

            Log::error('Account setup task generic error', [
                'task_id' => $this->taskId,
                'account_id' => $account->id,
                'task_type' => $task->task_type,
                'error' => $error,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Handle RPC error (reused from TelegramMtprotoPoolService pattern).
     */
    private function handleRpcError(MtprotoTelegramAccount $account, RPCErrorException $e): array
    {
        $rpc = strtoupper((string) ($e->rpc ?? ''));
        $msg = strtoupper((string) $e->getMessage());
        $code = $rpc !== '' ? $rpc : $msg;

        // FLOOD_WAIT_X
        if (preg_match('/FLOOD_WAIT_(\d+)/', $code, $m) || preg_match('/FLOOD_WAIT_(\d+)/', $msg, $m)) {
            $waitSeconds = (int) ($m[1] ?? 60);
            $waitSeconds = min($waitSeconds + 3, 3600); // buffer

            $account->setCooldown($waitSeconds);

            Log::info('MTProto account hit FLOOD_WAIT in setup task', [
                'account_id' => $account->id,
                'wait_seconds' => $waitSeconds,
            ]);

            return [
                'retry' => true,
                'error_code' => 'FLOOD_WAIT',
                'wait_seconds' => $waitSeconds,
            ];
        }

        // Permanent auth errors
        $permanentCodes = [
            'AUTH_KEY_UNREGISTERED',
            'SESSION_REVOKED',
            'USER_DEACTIVATED',
            'PHONE_NUMBER_BANNED',
            'USER_DEACTIVATED_BAN',
        ];

        foreach ($permanentCodes as $pat) {
            if (str_contains($code, $pat) || str_contains($msg, $pat)) {
                $account->disable($pat);

                Log::warning('MTProto account permanently disabled in setup task', [
                    'account_id' => $account->id,
                    'error_code' => $pat,
                ]);

                return [
                    'retry' => false,
                    'error_code' => $pat,
                    'permanent' => true,
                ];
            }
        }

        // PEER_FLOOD
        if (str_contains($code, 'PEER_FLOOD') || str_contains($msg, 'PEER_FLOOD')) {
            $hours = 3;
            $account->setCooldown($hours * 3600);

            Log::warning('MTProto account hit PEER_FLOOD in setup task', [
                'account_id' => $account->id,
                'cooldown_hours' => $hours,
            ]);

            return [
                'retry' => true,
                'error_code' => 'PEER_FLOOD',
                'wait_seconds' => $hours * 3600,
            ];
        }

        // Unknown RPC error
        return [
            'retry' => true,
            'error_code' => 'MTPROTO_RPC',
        ];
    }

    /**
     * Handle task failure and update task/account accordingly.
     */
    private function handleTaskFailure(
        MtprotoAccountTask $task,
        MtprotoTelegramAccount $account,
        string $errorCode,
        string $error,
        ?array $handled
    ): void {
        $task->increment('attempts');

        // Check if permanent failure
        $isPermanent = ($handled['permanent'] ?? false) || in_array($errorCode, [
            'AUTH_KEY_UNREGISTERED',
            'SESSION_REVOKED',
            'USER_DEACTIVATED',
            'PHONE_NUMBER_BANNED',
            'USER_DEACTIVATED_BAN',
        ], true);

        if ($isPermanent) {
            $task->update([
                'status' => MtprotoAccountTask::STATUS_FAILED,
                'last_error_code' => $errorCode,
                'last_error' => $error,
            ]);

            Log::warning('TASK_FAIL', [
                'task_id' => $task->id,
                'account_id' => $account->id,
                'task_type' => $task->task_type,
                'error_code' => $errorCode,
                'permanent' => true,
            ]);

            return;
        }

        // Calculate retry delay
        $waitSeconds = $handled['wait_seconds'] ?? null;
        if ($waitSeconds === null) {
            // Use backoff based on attempts
            $backoffArray = config('telegram_mtproto.setup.retry.backoff_seconds', [60, 300, 900, 3600]);
            $attemptIndex = min($task->attempts - 1, count($backoffArray) - 1);
            $waitSeconds = $backoffArray[$attemptIndex] ?? 3600;
        }

        $retryAt = now()->addSeconds($waitSeconds);

        $task->update([
            'status' => MtprotoAccountTask::STATUS_RETRY,
            'retry_at' => $retryAt,
            'last_error_code' => $errorCode,
            'last_error' => $error,
        ]);

        // Reschedule job
        self::dispatch($task->id)
            ->delay($retryAt)
            ->onQueue($this->getQueueForTaskType($task->task_type))
            ->afterCommit();

        Log::info('TASK_FAIL (retry scheduled)', [
            'task_id' => $task->id,
            'account_id' => $account->id,
            'task_type' => $task->task_type,
            'error_code' => $errorCode,
            'retry_at' => $retryAt->toDateTimeString(),
            'attempts' => $task->attempts,
        ]);
    }

    /**
     * Get queue name for task type.
     */
    private function getQueueForTaskType(string $taskType): string
    {
        $mediaTypes = MtprotoAccountTask::getMediaTaskTypes();

        if (in_array($taskType, $mediaTypes, true)) {
            return 'tg-setup-media';
        }

        return 'tg-setup-fast';
    }

    public function failed(Throwable $exception): void
    {
        $task = MtprotoAccountTask::query()->find($this->taskId);

        if ($task) {
            $task->update([
                'status' => MtprotoAccountTask::STATUS_RETRY,
                'last_error_code' => 'JOB_FAILED',
                'last_error' => $exception->getMessage(),
            ]);
        }

        Log::error('RunAccountSetupTaskJob failed', [
            'task_id' => $this->taskId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
