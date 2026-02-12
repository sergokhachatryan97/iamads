<?php

namespace App\Jobs;

use App\Models\TelegramAccount;
use App\Models\TelegramUnsubscribeTask;
use App\Services\Provider\ProviderClient;
use App\Services\Telegram\TelegramAccountCapService;
use App\Services\Telegram\TelegramAccountCooldownService;
use App\Services\Telegram\TelegramActionDedupeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ProcessUnsubscribeTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    public function __construct(public int $taskId) {}

    public function handle(
        ProviderClient $client,
        TelegramAccountCapService $capService,
        TelegramAccountCooldownService $cooldownService,
        TelegramActionDedupeService $dedupeService
    ): void {
        Redis::throttle('provider:unsubscribe')
            ->allow((int) config('services.provider.rate_limit_per_second', 5))
            ->every(1)
            ->then(function () use ($client, $capService, $cooldownService, $dedupeService) {
                $this->process($client, $capService, $cooldownService, $dedupeService);
            }, function () {
                $this->release(1);
            });
    }

    private function process(
        ProviderClient $client,
        TelegramAccountCapService $capService,
        TelegramAccountCooldownService $cooldownService,
        TelegramActionDedupeService $dedupeService
    ): void {
        // Claim task atomically
        $claimed = TelegramUnsubscribeTask::query()
            ->whereKey($this->taskId)
            ->where('status', 'pending')
            ->where('due_at', '<=', now())
            ->update([
                'status' => 'processing',
            ]);

        if ($claimed === 0) {
            // Already processed or not due yet
            return;
        }

        $task = TelegramUnsubscribeTask::query()
            ->with('telegramAccount')
            ->find($this->taskId);

        if (!$task || !$task->telegramAccount) {
            Log::warning('Unsubscribe task not found or account missing', ['task_id' => $this->taskId]);
            return;
        }

        $account = $task->telegramAccount;
        $action = 'unsubscribe';

        try {
            // Check daily cap
            if (!$capService->tryConsume($account->id, $action)) {
                // Cap reached, reschedule for later today
                $task->update(['status' => 'pending']);
                $this->release(3600); // Retry in 1 hour
                Log::warning('Unsubscribe task cap reached, rescheduling', [
                    'task_id' => $this->taskId,
                    'account_id' => $account->id,
                ]);
                return;
            }

            // Check cooldown
            if (!$cooldownService->tryClaim($account->id, $action)) {
                // In cooldown, reschedule
                $remaining = $cooldownService->remainingSeconds($account->id, $action);
                $task->update(['status' => 'pending']);
                $this->release($remaining);
                Log::debug('Unsubscribe task in cooldown, rescheduling', [
                    'task_id' => $this->taskId,
                    'account_id' => $account->id,
                    'remaining_seconds' => $remaining,
                ]);
                return;
            }

            // Execute unsubscribe via provider
            // Note: We need link from subject or store it in task meta
            // For now, we'll need to reconstruct or store link_hash mapping
            // This is a simplified version - you may need to adjust based on your provider API
            $result = $client->executeTelegramUnsubscribe(
                $account,
                $task->link_hash,
                $task->subject_type,
                $task->subject_id
            );

            if ($result['ok']) {
                // Success: mark task done, decrement subscription_count, mark subscribe reversed
                $task->update([
                    'status' => 'done',
                    'processed_at' => now(),
                    'last_error' => null,
                ]);

                // Decrement subscription count (ensure >= 0)
                $account->decrement('subscription_count', 1);
                if ($account->subscription_count < 0) {
                    $account->update(['subscription_count' => 0]);
                }

                // Mark subscribe action as reversed (if reallow_after_unsubscribe is true)
                $subscribePolicy = config('telegram.action_policies.subscribe', []);
                if ($subscribePolicy['reallow_after_unsubscribe'] ?? false) {
                    $dedupeService->markReversed($account->id, $task->link_hash, 'subscribe');
                }

                Log::info('Unsubscribe task completed', [
                    'task_id' => $this->taskId,
                    'account_id' => $account->id,
                    'link_hash' => $task->link_hash,
                ]);
            } else {
                // Failure: mark as failed and reschedule with backoff
                $task->update([
                    'status' => 'failed',
                    'last_error' => $result['error'] ?? 'Unknown error',
                    'processed_at' => now(),
                ]);

                Log::warning('Unsubscribe task failed', [
                    'task_id' => $this->taskId,
                    'account_id' => $account->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                // Throw to trigger retry/backoff
                throw new \RuntimeException("Unsubscribe failed: " . ($result['error'] ?? 'Unknown error'));
            }
        } catch (Throwable $e) {
            // On exception, mark as failed
            $task->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('Unsubscribe task exception', [
                'task_id' => $this->taskId,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $task = TelegramUnsubscribeTask::query()->find($this->taskId);

        if ($task) {
            $task->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
                'processed_at' => now(),
            ]);
        }

        Log::error('ProcessUnsubscribeTaskJob failed', [
            'task_id' => $this->taskId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
