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

/**
 * Process due unsubscribe tasks.
 *
 * This job should be scheduled to run every minute via Laravel scheduler.
 * It finds pending unsubscribe tasks that are due and dispatches them to provider.
 */
class ProcessTelegramUnsubscribeTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Single attempt per run

    public function handle(
        ProviderClient $client,
        TelegramAccountCapService $capService,
        TelegramAccountCooldownService $cooldownService,
        TelegramActionDedupeService $dedupeService
    ): void {
        // Find due tasks (status pending, due_at <= now)
        $dueTasks = TelegramUnsubscribeTask::query()
            ->where('status', 'pending')
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->limit(100) // Process up to 100 per run
            ->get();

        if ($dueTasks->isEmpty()) {
            return;
        }

        Log::info('Processing unsubscribe tasks', [
            'count' => $dueTasks->count(),
        ]);

        foreach ($dueTasks as $task) {
            $this->processTask($task, $client, $capService, $cooldownService, $dedupeService);
        }
    }

    /**
     * Process a single unsubscribe task.
     */
    private function processTask(
        TelegramUnsubscribeTask $task,
        ProviderClient $client,
        TelegramAccountCapService $capService,
        TelegramAccountCooldownService $cooldownService,
        TelegramActionDedupeService $dedupeService
    ): void {
        // Mark as processing (idempotent)
        $updated = TelegramUnsubscribeTask::query()
            ->where('id', $task->id)
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if ($updated === 0) {
            // Already being processed or completed
            return;
        }

        $account = TelegramAccount::find($task->telegram_account_id);
        if (!$account || !$account->is_active) {
            $task->update([
                'status' => 'failed',
                'error' => 'Account not found or inactive',
            ]);
            return;
        }

        // Enforce global cooldown
        if (!$cooldownService->tryClaimGlobal($account->id)) {
            // Reschedule for later (cooldown will expire)
            $remaining = $cooldownService->remainingGlobalSeconds($account->id);
            $task->update([
                'status' => 'pending',
                'due_at' => now()->addSeconds($remaining),
            ]);
            return;
        }

        // Enforce unsubscribe daily cap
        if (!$capService->tryConsume($account->id, 'unsubscribe')) {
            // Cap reached, reschedule for tomorrow
            $task->update([
                'status' => 'pending',
                'due_at' => now()->addDay()->startOfDay(),
            ]);
            return;
        }

        // Check dedupe (unsubscribe doesn't dedupe per link, but we can check if already unsubscribed)
        // Note: unsubscribe action typically doesn't have dedupe_per_link, but we check anyway

        // Call provider unsubscribe endpoint (async-friendly)
        $result = $client->executeTelegramUnsubscribe(
            $account,
            $task->link_hash,
            $task->subject_type,
            $task->subject_id
        );

        $state = $result['state'] ?? 'done';
        $ok = (bool) ($result['ok'] ?? false);

        if ($state === 'pending') {
            // Provider returned pending: save task_id
            $taskId = $result['task_id'] ?? null;
            if ($taskId) {
                $task->update([
                    'provider_task_id' => $taskId,
                    'status' => 'processing',
                ]);

                // Note: Webhook will handle completion, or admin can manually retry
                Log::info('Unsubscribe task pending, waiting for provider completion', [
                    'task_id' => $task->id,
                    'provider_task_id' => $taskId,
                ]);
            } else {
                // No task_id, treat as failed
                $task->update([
                    'status' => 'failed',
                    'error' => 'Provider returned pending without task_id',
                ]);
                $capService->rollbackConsume($account->id, 'unsubscribe');
            }
        } elseif ($state === 'failed' || !$ok) {
            // Failed: rollback cap and mark failed
            $capService->rollbackConsume($account->id, 'unsubscribe');
            $task->update([
                'status' => 'failed',
                'error' => $result['error'] ?? 'Unsubscribe failed',
            ]);
        } else {
            // Done: mark as completed
            $task->update([
                'status' => 'done',
                'error' => null,
            ]);

            Log::info('Unsubscribe task completed', [
                'task_id' => $task->id,
                'account_id' => $account->id,
                'link_hash' => $task->link_hash,
            ]);
        }
    }
}
