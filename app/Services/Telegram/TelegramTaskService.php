<?php

namespace App\Services\Telegram;

use App\Models\ClientServiceQuota;
use App\Models\Order;
use App\Models\TelegramAccount;
use App\Models\TelegramTask;
use App\Models\TelegramUnsubscribeTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Service for managing Telegram tasks in provider pull architecture.
 *
 * Handles:
 * - Task generation from eligible orders
 * - Account selection with reserve claim
 * - Task leasing
 * - Task reporting and finalization
 */
class TelegramTaskService
{
    public function __construct(
        private TelegramAccountClaimService $claimService,
        private TelegramActionDedupeService $dedupeService,
        private TelegramStepCompletionService $completionService,
    ) {}

    /**
     * Generate tasks from eligible orders and due unsubscribe tasks.
     *
     * Phase 1: Finds orders where:
     * - next_run_at <= now
     * - remains > 0
     * - status in (AWAITING, IN_PROGRESS, PENDING)
     *
     * Phase 2: Finds due unsubscribe tasks where:
     * - status = 'pending'
     * - due_at <= now
     *
     * @param int $maxTasks Maximum number of tasks to generate
     * @return int Number of tasks generated
     */
    public function generateTasks(int $maxTasks = 1000): int
    {
        $generated = 0;
        $batchSize = 50;

        // PHASE 1: Generate tasks from eligible orders
        $allOrders = Order::query()
            ->whereIn('status', [
                Order::STATUS_AWAITING,
                Order::STATUS_IN_PROGRESS,
                Order::STATUS_PENDING,
            ])
//            ->whereNull('provider')
            ->where('remains', '>', 0)
            ->limit($batchSize * 2) // Fetch more to account for filtering
            ->get();

        $orders = $allOrders->filter(function ($order) {
            $providerPayload = $order->provider_payload ?? [];
            $executionMeta = $providerPayload['execution_meta'] ?? [];
            $nextRunAt = $executionMeta['next_run_at'] ?? null;

            if ($nextRunAt === null) {
                return true; // No next_run_at set, eligible
            }

            try {
                $nextRunAtCarbon = \Carbon\Carbon::parse($nextRunAt);
                return $nextRunAtCarbon->lte(now());
            } catch (\Throwable) {
                return true; // Invalid date, treat as eligible
            }
        })->take($batchSize);

        foreach ($orders as $order) {
            if ($generated >= $maxTasks) {
                break;
            }

            try {
                $task = $this->generateTaskForOrder($order);
                if ($task) {
                    $generated++;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to generate task for order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // PHASE 2: Generate tasks from due unsubscribe tasks
        if ($generated < $maxTasks) {
            $remaining = $maxTasks - $generated;
            $unsubscribeGenerated = $this->generateUnsubscribeTasks($remaining);
            $generated += $unsubscribeGenerated;
        }

        // PHASE 3: Generate tasks from eligible quotas
        if ($generated < $maxTasks) {
            $remaining = $maxTasks - $generated;
            $quotaGenerated = $this->generateQuotaTasks($remaining);
            $generated += $quotaGenerated;
        }

        return $generated;
    }

    /**
     * Generate a single task for an order.
     *
     * @param Order $order
     * @return TelegramTask|null
     */
    private function generateTaskForOrder(Order $order): ?TelegramTask
    {
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = $providerPayload['execution_meta'] ?? [];

        if (!is_array($executionMeta)) {
            return null;
        }

        // Check next_run_at
        $nextRunAt = $executionMeta['next_run_at'] ?? null;
        if ($nextRunAt) {
            $nextRunAtCarbon = \Carbon\Carbon::parse($nextRunAt);
            if ($nextRunAtCarbon->isFuture()) {
                return null;
            }
        }

        // Get action and policy
        $action = (string) ($executionMeta['action'] ?? 'subscribe');
        $policy = config("telegram.action_policies.{$action}", []);
        $dedupePerLink = (bool) ($policy['dedupe_per_link'] ?? true);

        // Get link data
        $telegramData = $providerPayload['telegram'] ?? [];
        $parsed = is_array($telegramData['parsed'] ?? null) ? $telegramData['parsed'] : [];
        $linkHash = $this->dedupeService->normalizeAndHashLink($parsed);

        $service = $order->service;
        $executor = $service ? $service->executor() : 'remote_provider';

        // Select account with reserve claim (manual => only accounts with mtproto_account_id)
        [$account, $stats] = $this->selectAccountWithReserve(
            $action,
            $order,
            $linkHash,
            $dedupePerLink,
            $executor
        );

        if (!$account) {
            $errorMessage = $executor === 'local_mtproto'
                ? 'No local MTProto accounts available'
                : 'No available Telegram account (cooldown/cap/dedupe constraints)';
            $order->update([
                'status' => Order::STATUS_PENDING,
                'provider_last_error' => $errorMessage,
                'provider_last_error_at' => now(),
            ]);
            return null;
        }

        $templateKey = $service?->template_key;
        $policyKey = $service?->policyKey();
        $linkKind = $parsed['kind'] ?? null;
        $peerType = $telegramData['chat_type'] ?? null;
        $postId = $parsed['post_id'] ?? null;
        $startParam = $parsed['start'] ?? null;

        $payload = $this->buildTaskPayload([
            'action' => $action,
            'link' => $order->link,
            'link_hash' => $linkHash,
            'per_call' => (int) ($executionMeta['per_call'] ?? 1),
            'executor' => $executor,
            'template_key' => $templateKey,
            'policy_key' => $policyKey,
            'link_kind' => $linkKind,
            'peer_type' => $peerType,
            'post_id' => $postId,
            'start_param' => $startParam,
            'meta' => $executionMeta,
            'parsed' => $parsed,
            'subject' => ['type' => 'order', 'id' => $order->id],
        ]);

        // Create task (set both order_id and subject for backward compatibility)
        $task = TelegramTask::create([
            'order_id' => $order->id,
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'action' => $action,
            'link_hash' => $linkHash,
            'telegram_account_id' => $account->id,
            'provider_account_id' => $account->provider_account_id,
            'status' => TelegramTask::STATUS_QUEUED,
            'attempt' => 0,
            'payload' => $payload,
        ]);

        // Advance order.next_run_at
        $intervalSeconds = (int) ($executionMeta['interval_seconds'] ?? 60);
        $executionMeta['next_run_at'] = now()->addSeconds($intervalSeconds)->toDateTimeString();
        $providerPayload['execution_meta'] = $executionMeta;
        $order->update(['provider_payload' => $providerPayload]);

        Log::debug('Generated task for order', [
            'task_id' => $task->id,
            'order_id' => $order->id,
            'account_id' => $account->id,
            'action' => $action,
        ]);

        return $task;
    }

    /**
     * Build standardized task payload for local worker / executors.
     * Ensures payload includes template_key, action, policy_key, link, link_hash,
     * per_call, link_kind, peer_type, post_id (when post), start_param (when needed).
     *
     * @param array $params action, link, link_hash, per_call, executor?, template_key?, policy_key?, link_kind?, peer_type?, post_id?, start_param?, meta?, parsed?, subject?
     * @return array
     */
    private function buildTaskPayload(array $params): array
    {
        $action = (string) ($params['action'] ?? 'subscribe');
        $link = $params['link'] ?? null;
        $linkHash = (string) ($params['link_hash'] ?? '');
        $perCall = (int) ($params['per_call'] ?? 1);
        $executor = (string) ($params['executor'] ?? 'remote_provider');
        $templateKey = $params['template_key'] ?? null;
        $policyKey = $params['policy_key'] ?? null;
        $linkKind = $params['link_kind'] ?? null;
        $peerType = $params['peer_type'] ?? null;
        $postId = $params['post_id'] ?? null;
        $startParam = $params['start_param'] ?? null;
        $meta = $params['meta'] ?? [];
        $parsed = $params['parsed'] ?? [];
        $subject = $params['subject'] ?? null;

        $payload = [
            'template_key' => $templateKey,
            'action' => $action,
            'policy_key' => $policyKey,
            'executor' => $executor,
            'link' => $link,
            'link_hash' => $linkHash,
            'per_call' => $perCall,
            'link_kind' => $linkKind,
            'peer_type' => $peerType,
            'post_id' => $postId,
            'start_param' => $startParam,
            'meta' => $meta,
            'parsed' => $parsed,
        ];
        if ($subject !== null) {
            $payload['subject'] = $subject;
        }
        return $payload;
    }

    /**
     * Select account with reserve claim (two-phase: RESERVE only).
     * When executor is local_mtproto, only considers accounts with mtproto_account_id set.
     *
     * @param string $action
     * @param Order $order
     * @param string $linkHash
     * @param bool $dedupePerLink
     * @param string $executor 'local_mtproto'|'remote_provider'
     * @return array{TelegramAccount|null, array}
     */
    private function selectAccountWithReserve(
        string $action,
        Order $order,
        string $linkHash,
        bool $dedupePerLink,
        string $executor = 'remote_provider'
    ): array {
        $batchSize = (int) config('telegram.account_selection.batch_size', 200);
        $maxScanLimit = (int) config('telegram.account_selection.max_scan_limit', 2000);

        $cursorKey = 'tg:acct_cursor:global';
        $startFromId = (int) (Redis::get($cursorKey) ?? 0);

        $account = null;
        $scanned = 0;
        $skippedCap = 0;
        $skippedCooldown = 0;
        $skippedDedupe = 0;
        $skippedLock = 0;

        $needsCap = in_array($action, ['subscribe', 'unsubscribe'], true);

        $tryCandidate = function ($candidate) use (
            &$account,
            &$scanned,
            &$skippedCap,
            &$skippedCooldown,
            &$skippedDedupe,
            &$skippedLock,
            $action,
            $linkHash,
            $dedupePerLink,
            $needsCap
        ): bool {
            $scanned++;

            // Reserve claim (only sets lock, no cap/cooldown consumption)
            $reserveResult = $this->claimService->reserveClaim(
                $candidate->id,
                $action,
                $linkHash,
                $dedupePerLink
            );

            if (!($reserveResult['success'] ?? false)) {
                $reason = (string) ($reserveResult['reason'] ?? 'unknown');

                if (in_array($reason, ['dedupe', 'dedupe_race', 'state_already_subscribed', 'state_not_subscribed'], true)) {
                    $skippedDedupe++;
                    return false;
                }

                if ($reason === 'locked') {
                    $skippedLock++;
                    return false;
                }

                // Unknown reason
                $skippedDedupe++;
                return false;
            }

            $account = $candidate;
            return true;
        };

        $baseSelect = ['id', 'provider_account_id', 'phone', 'subscription_count', 'is_active'];

        // PASS 1: scan from cursor -> max
        TelegramAccount::query()
            ->select($baseSelect)
            ->where('is_active', true)
            ->where('subscription_count', '<', 400)
            ->when($executor === 'local_mtproto', fn ($q) => $q->whereNotNull('mtproto_account_id'))
            ->where('id', '>', $startFromId)
            ->orderBy('id')
            ->chunkById($batchSize, function ($candidates) use (&$account, &$scanned, $tryCandidate, $maxScanLimit) {
                foreach ($candidates as $candidate) {
                    if ($scanned >= $maxScanLimit) {
                        return false;
                    }
                    if ($tryCandidate($candidate)) {
                        return false;
                    }
                }
                return true;
            });

        // PASS 2: wrap-around
        if (!$account && $startFromId > 0 && $scanned < $maxScanLimit) {
            TelegramAccount::query()
                ->select($baseSelect)
                ->where('is_active', true)
                ->where('subscription_count', '<', 400)
                ->when($executor === 'local_mtproto', fn ($q) => $q->whereNotNull('mtproto_account_id'))
                ->where('id', '<=', $startFromId)
                ->orderBy('id')
                ->chunkById($batchSize, function ($candidates) use (&$account, &$scanned, $tryCandidate, $maxScanLimit) {
                    foreach ($candidates as $candidate) {
                        if ($scanned >= $maxScanLimit) {
                            return false;
                        }
                        if ($tryCandidate($candidate)) {
                            return false;
                        }
                    }
                    return true;
                });
        }

        // Update cursor
        if ($account) {
            Redis::set($cursorKey, (string) $account->id);
        }

        return [
            $account,
            [
                'cursor_start' => $startFromId,
                'scanned' => $scanned,
                'skipped_cap' => $skippedCap,
                'skipped_cooldown' => $skippedCooldown,
                'skipped_dedupe' => $skippedDedupe,
                'skipped_lock' => $skippedLock,
            ],
        ];
    }

    /**
     * Lease tasks for provider pull.
     *
     * @param int $limit Maximum number of tasks to lease
     * @return array Array of task data for provider
     */
    public function leaseTasks(int $limit = 1000): array
    {
        $leaseTtl = 60;
        $leasedUntil = now()->addSeconds($leaseTtl);

        $tasks = DB::transaction(function () use ($limit, $leasedUntil) {
            $q = TelegramTask::query()
                ->where(function ($q) {
                    $q->where('status', TelegramTask::STATUS_QUEUED)
                        ->orWhere(function ($q2) {
                            $q2->where('status', TelegramTask::STATUS_LEASED)
                                ->where('leased_until', '<=', now());
                        });
                })
                ->limit($limit)
                ->lockForUpdate();

            // remote provider tasks + legacy null executor
            $this->whereExecutor($q, 'remote_provider', allowNull: true);

            return $q->get();
        });

        $leased = [];
        foreach ($tasks as $task) {
            if (!$task->isEligibleForLease()) continue;

            $task->update([
                'status' => TelegramTask::STATUS_LEASED,
                'leased_until' => $leasedUntil,
            ]);

            $payload = $task->payload;
            $account = $task->telegramAccount;

            $subject = $task->subject;
            $leased[] = [
                'task_id' => $task->id,
                'order_id' => $task->order_id,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject ? $subject->id : null,
                'action' => $task->action,
                'link' => $payload['link'] ?? null,
                'account' => [
                    'provider_account_id' => $task->provider_account_id ?? $account?->provider_account_id,
                    'phone' => $account?->phone ?? null,
                ],
                'per_call' => (int) ($payload['per_call'] ?? 1),
                'post_id' => $payload['post_id'] ?? null,
                'link_hash' => $task->link_hash,
                'attempt' => $task->attempt,
            ];
        }

        return $leased;
    }


    /**
     * Atomically lease tasks for local worker execution.
     * Uses a transaction and FOR UPDATE to prevent double-leasing.
     *
     * @param int $limit Maximum number of tasks to lease
     * @param int|null $leaseTtlSeconds Lease TTL (default from config)
     * @return \Illuminate\Support\Collection<int, TelegramTask>
     */
    public function leaseTasksForLocalWorker(int $limit = 200, ?int $leaseTtlSeconds = null): \Illuminate\Support\Collection
    {
        $leaseTtlSeconds = $leaseTtlSeconds ?? (int) config('telegram.local_worker.lease_ttl_seconds', 60);
        $leasedUntil = now()->addSeconds($leaseTtlSeconds);

        return DB::transaction(function () use ($limit, $leasedUntil) {
            $q = TelegramTask::query()
                ->where(function ($q) {
                    $q->where('status', TelegramTask::STATUS_QUEUED)
                        ->orWhere(function ($q2) {
                            $q2->where('status', TelegramTask::STATUS_LEASED)
                                ->where('leased_until', '<=', now());
                        });
                })
                ->whereNotIn('status', [TelegramTask::STATUS_DONE, TelegramTask::STATUS_FAILED])
                ->limit($limit)
                ->lockForUpdate();

            $this->whereExecutor($q, 'local_mtproto', allowNull: false);

            $tasks = $q->get();

            $leased = collect();
            foreach ($tasks as $task) {
                if (!$task->isEligibleForLease()) continue;

                $task->update([
                    'status' => TelegramTask::STATUS_LEASED,
                    'leased_until' => $leasedUntil,
                ]);
                $leased->push($task);
            }

            return $leased;
        });
    }


    private function whereExecutor($q, string $executor, bool $allowNull = false): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            if ($allowNull) {
                $q->whereRaw("(json_extract(payload, '$.executor') IS NULL OR json_extract(payload, '$.executor') = ?)", [$executor]);
            } else {
                $q->whereRaw("json_extract(payload, '$.executor') = ?", [$executor]);
            }
            return;
        }

        // mysql / mariadb
        if ($allowNull) {
            $q->whereRaw("(JSON_EXTRACT(payload, '$.executor') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.executor')) = ?)", [$executor]);
        } else {
            $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.executor')) = ?", [$executor]);
        }
    }



    /**
     * Report task result from provider.
     *
     * @param string $taskId
     * @param array $result {state, ok, error, retry_after, provider_task_id, data}
     * @return array{ok: bool, error?: string}
     */
    public function reportTaskResult(string $taskId, array $result): array
    {
        $task = TelegramTask::query()->find($taskId);

        if (!$task) {
            return ['ok' => false, 'error' => 'Task not found'];
        }

        // Idempotency check: if already finalized, return ok without double-applying
        if ($task->isFinalized()) {
            Log::info('Task already finalized, ignoring duplicate report', [
                'task_id' => $taskId,
                'current_status' => $task->status,
            ]);
            return ['ok' => true];
        }

        $state = (string) ($result['state'] ?? 'done');
        $ok = (bool) ($result['ok'] ?? false);
        $error = $result['error'] ?? null;
        $retryAfter = $result['retry_after'] ?? null;
        $providerTaskId = $result['provider_task_id'] ?? null;

        // Get subject (Order or ClientServiceQuota)
        $subject = $task->subject;
        if (!$subject) {
            return ['ok' => false, 'error' => 'Subject not found'];
        }

        $account = $task->telegramAccount;
        if (!$account) {
            return ['ok' => false, 'error' => 'Telegram account not found'];
        }

        // Handle quota vs order differently
        if ($subject instanceof ClientServiceQuota) {
            return $this->handleQuotaTaskReport($task, $subject, $account, $state, $ok, $error, $retryAfter);
        }

        // Handle order task (existing logic)
        $order = $subject;

        // Update task
        $task->update([
            'result' => $result,
            'status' => $state === 'pending' ? TelegramTask::STATUS_PENDING : ($ok ? TelegramTask::STATUS_DONE : TelegramTask::STATUS_FAILED),
        ]);

        // Handle pending state
        if ($state === 'pending') {
            // Task is pending, provider will report later
            // Keep task in LEASED status, extend lease if needed
            if ($retryAfter) {
                $task->update([
                    'status' => TelegramTask::STATUS_PENDING,
                    'leased_until' => now()->addSeconds(max(60, min(300, (int) $retryAfter))),
                ]);
            }
            return ['ok' => true];
        }

        // Handle success (done + ok)
        if ($ok && $state === 'done') {
            // Get action from task (more reliable than execution_meta)
            $action = $task->action;

            // Commit claim: consume cooldown, cap, set state
            $providerPayload = $order->provider_payload ?? [];
            $executionMeta = $providerPayload['execution_meta'] ?? [];
            $policy = config("telegram.action_policies.{$action}", []);
            $dedupePerLink = (bool) ($policy['dedupe_per_link'] ?? true);
            $needsCap = in_array($action, ['subscribe', 'unsubscribe'], true);

            $commitResult = $this->claimService->commitClaim(
                $account->id,
                $action,
                $task->link_hash,
                $dedupePerLink,
                $needsCap
            );

            if (!($commitResult['success'] ?? false)) {
                Log::warning('Failed to commit claim', [
                    'task_id' => $taskId,
                    'account_id' => $account->id,
                    'reason' => $commitResult['reason'] ?? 'unknown',
                ]);
            }

            // Use existing completion service to update order
            // Pull mode is enabled by default, so completion service won't dispatch
            $telegramData = $providerPayload['telegram'] ?? [];
            $parsed = is_array($telegramData['parsed'] ?? null) ? $telegramData['parsed'] : [];

            $this->completionService->handle(
                $order,
                $account->id,
                $action,
                $task->link_hash,
                $result,
                $parsed
            );

            $task->update(['status' => TelegramTask::STATUS_DONE]);

            // Schedule auto-unsubscribe if this was a successful subscribe action
            if ($action === 'subscribe') {
                $this->scheduleUnsubscribeTask($order, $account, $task);
            }

            // Finalize unsubscribe task if this was a successful unsubscribe action
            if ($action === 'unsubscribe') {
                $this->finalizeUnsubscribeTask($order, $account, $task);
            }
        } else {
            // Handle failure
            // Rollback reserve (release lock only)
            $action = $task->action;

            $this->claimService->rollbackReserve($account->id, $action);

            // Update order status
            $order->update([
                'status' => Order::STATUS_PENDING,
                'provider_last_error' => $error ?? 'Provider task failed',
                'provider_last_error_at' => now(),
            ]);

            $task->update(['status' => TelegramTask::STATUS_FAILED]);

            // Handle unsubscribe task failure: revert to pending for retry
            if ($action === 'unsubscribe') {
                $this->handleUnsubscribeTaskFailure($order, $account, $task, $error);
            }
        }

        return ['ok' => true];
    }

    /**
     * Schedule an unsubscribe task when a subscribe task completes successfully.
     *
     * @param Order $order
     * @param TelegramAccount $account
     * @param TelegramTask $task The completed subscribe task
     */
    private function scheduleUnsubscribeTask(Order $order, TelegramAccount $account, TelegramTask $task): void
    {
        // Get duration_days from service
        $service = $order->service;
        if (!$service) {
            return;
        }

        $durationDays = (int) ($service->duration_days ?? 0);
        if ($durationDays <= 0) {
            return; // No auto-unsubscribe needed
        }

        // Use deterministic due_at based on task.created_at (for idempotency)
        $dueAt = $task->created_at->copy()->addDays($durationDays);

        // Use updateOrCreate with unique constraint to ensure idempotency
        // If duplicate report comes in, it will update existing row instead of creating duplicate
        TelegramUnsubscribeTask::updateOrCreate(
            [
                'telegram_account_id' => $account->id,
                'link_hash' => $task->link_hash,
                'due_at' => $dueAt,
            ],
            [
                'status' => TelegramUnsubscribeTask::STATUS_PENDING,
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'error' => null,
            ]
        );

        Log::debug('Scheduled unsubscribe task', [
            'order_id' => $order->id,
            'account_id' => $account->id,
            'link_hash' => $task->link_hash,
            'due_at' => $dueAt->toDateTimeString(),
            'duration_days' => $durationDays,
        ]);
    }

    /**
     * Finalize an unsubscribe task when unsubscribe task completes.
     *
     * @param Order $order
     * @param TelegramAccount $account
     * @param TelegramTask $task The completed unsubscribe task
     */
    private function finalizeUnsubscribeTask(Order $order, TelegramAccount $account, TelegramTask $task): void
    {
        // Find matching unsubscribe task
        $unsubscribeTask = TelegramUnsubscribeTask::query()
            ->where('telegram_account_id', $account->id)
            ->where('link_hash', $task->link_hash)
            ->where('subject_type', Order::class)
            ->where('subject_id', $order->id)
            ->where('status', TelegramUnsubscribeTask::STATUS_PROCESSING)
            ->first();

        if (!$unsubscribeTask) {
            Log::warning('Unsubscribe task not found for finalization', [
                'order_id' => $order->id,
                'account_id' => $account->id,
                'link_hash' => $task->link_hash,
                'telegram_task_id' => $task->id,
            ]);
            return;
        }

        // Mark as done
        $unsubscribeTask->update([
            'status' => TelegramUnsubscribeTask::STATUS_DONE,
            'error' => null,
        ]);

        Log::debug('Finalized unsubscribe task', [
            'unsubscribe_task_id' => $unsubscribeTask->id,
            'order_id' => $order->id,
            'account_id' => $account->id,
        ]);
    }

    /**
     * Generate tasks from due unsubscribe tasks.
     *
     * @param int $maxTasks Maximum number of unsubscribe tasks to process
     * @return int Number of tasks generated
     */
    private function generateUnsubscribeTasks(int $maxTasks): int
    {
        $generated = 0;

        // Find due unsubscribe tasks
        $dueUnsubscribeTasks = TelegramUnsubscribeTask::query()
            ->where('status', TelegramUnsubscribeTask::STATUS_PENDING)
            ->where('due_at', '<=', now())
            ->orderBy('due_at', 'asc')
            ->limit($maxTasks)
            ->get();

        foreach ($dueUnsubscribeTasks as $unsubscribeTask) {
            try {
                $task = $this->generateTaskFromUnsubscribeTask($unsubscribeTask);
                if ($task) {
                    $generated++;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to generate task from unsubscribe task', [
                    'unsubscribe_task_id' => $unsubscribeTask->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $generated;
    }

    /**
     * Generate a TelegramTask from a TelegramUnsubscribeTask.
     *
     * @param TelegramUnsubscribeTask $unsubscribeTask
     * @return TelegramTask|null
     */
    private function generateTaskFromUnsubscribeTask(TelegramUnsubscribeTask $unsubscribeTask): ?TelegramTask
    {
        $account = $unsubscribeTask->telegramAccount;
        if (!$account) {
            Log::warning('Telegram account not found for unsubscribe task', [
                'unsubscribe_task_id' => $unsubscribeTask->id,
            ]);
            return null;
        }

        $order = $unsubscribeTask->subject;
        if (!$order || !($order instanceof Order)) {
            Log::warning('Order not found for unsubscribe task', [
                'unsubscribe_task_id' => $unsubscribeTask->id,
            ]);
            return null;
        }

        // Reserve the SAME account for unsubscribe action
        $action = 'unsubscribe';
        $linkHash = $unsubscribeTask->link_hash;
        $dedupePerLink = (bool) (config("telegram.action_policies.{$action}.dedupe_per_link", true));

        $reserveResult = $this->claimService->reserveClaim(
            $account->id,
            $action,
            $linkHash,
            $dedupePerLink
        );

        if (!($reserveResult['success'] ?? false)) {
            $reason = (string) ($reserveResult['reason'] ?? 'unknown');
            Log::debug('Failed to reserve account for unsubscribe task', [
                'unsubscribe_task_id' => $unsubscribeTask->id,
                'account_id' => $account->id,
                'reason' => $reason,
            ]);

            // Keep row as pending, will retry on next generation cycle
            return null;
        }

        // Get order link and parsed data
        $providerPayload = $order->provider_payload ?? [];
        $telegramData = $providerPayload['telegram'] ?? [];
        $parsed = is_array($telegramData['parsed'] ?? null) ? $telegramData['parsed'] : [];
        $executionMeta = $providerPayload['execution_meta'] ?? [];

        $service = $order->service;
        $executor = $service ? $service->executor() : 'remote_provider';
        $templateKey = $service?->template_key;
        $policyKey = $service?->policyKey();
        $linkKind = $parsed['kind'] ?? null;
        $peerType = $telegramData['chat_type'] ?? null;
        $postId = $parsed['post_id'] ?? null;

        $payload = $this->buildTaskPayload([
            'action' => 'unsubscribe',
            'link' => $order->link,
            'link_hash' => $linkHash,
            'per_call' => 1,
            'executor' => $executor,
            'template_key' => $templateKey,
            'policy_key' => $policyKey,
            'link_kind' => $linkKind,
            'peer_type' => $peerType,
            'post_id' => $postId,
            'start_param' => null,
            'meta' => $executionMeta,
            'parsed' => $parsed,
            'subject' => ['type' => 'order', 'id' => $order->id],
        ]);

        // Create TelegramTask
        $task = TelegramTask::create([
            'order_id' => $order->id,
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'action' => $action,
            'link_hash' => $linkHash,
            'telegram_account_id' => $account->id,
            'provider_account_id' => $account->provider_account_id,
            'status' => TelegramTask::STATUS_QUEUED,
            'attempt' => 0,
            'payload' => $payload,
        ]);

        // Mark unsubscribe task as processing and link to telegram_task
        $unsubscribeTask->update([
            'status' => TelegramUnsubscribeTask::STATUS_PROCESSING,
            'telegram_task_id' => $task->id,
        ]);

        Log::debug('Generated unsubscribe task from unsubscribe task', [
            'task_id' => $task->id,
            'unsubscribe_task_id' => $unsubscribeTask->id,
            'order_id' => $order->id,
            'account_id' => $account->id,
        ]);

        return $task;
    }

    /**
     * Handle unsubscribe task failure: revert to pending for retry or mark as failed.
     *
     * @param Order $order
     * @param TelegramAccount $account
     * @param TelegramTask $task The failed unsubscribe task
     * @param string|null $error Error message
     */
    private function handleUnsubscribeTaskFailure(Order $order, TelegramAccount $account, TelegramTask $task, ?string $error): void
    {
        // Find matching unsubscribe task
        $unsubscribeTask = TelegramUnsubscribeTask::query()
            ->where('telegram_account_id', $account->id)
            ->where('link_hash', $task->link_hash)
            ->where('subject_type', Order::class)
            ->where('subject_id', $order->id)
            ->where('status', TelegramUnsubscribeTask::STATUS_PROCESSING)
            ->first();

        if (!$unsubscribeTask) {
            return;
        }

        // Revert to pending for retry (with backoff: add 1 hour to due_at)
        $unsubscribeTask->update([
            'status' => TelegramUnsubscribeTask::STATUS_PENDING,
            'due_at' => now()->addHour(), // Backoff: retry in 1 hour
            'error' => $error,
            'telegram_task_id' => null, // Clear link to failed task
        ]);

        Log::debug('Reverted unsubscribe task to pending after failure', [
            'unsubscribe_task_id' => $unsubscribeTask->id,
            'order_id' => $order->id,
            'account_id' => $account->id,
            'error' => $error,
        ]);
    }

    /**
     * Generate tasks from eligible quotas.
     *
     * Finds quotas where:
     * - expires_at > now
     * - quantity_left > 0 OR orders_left > 0
     * - inspection ok
     * - execution_meta exists
     * - next_run_at <= now (or missing)
     *
     * @param int $maxTasks Maximum number of quota tasks to generate
     * @return int Number of tasks generated
     */
    private function generateQuotaTasks(int $maxTasks): int
    {
        $generated = 0;
        $batchSize = 50;

        // Find eligible quotas (DB-level filtering)
        $allQuotas = ClientServiceQuota::query()
            ->where('expires_at', '>', now())
            ->where(function ($q) {
                $q->where('quantity_left', '>', 0)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('orders_left')
                            ->where('orders_left', '>', 0);
                    });
            })
            ->limit($batchSize * 2) // Fetch more to account for filtering
            ->get();

        // Filter by inspection and next_run_at in PHP
        $quotas = $allQuotas->filter(function ($quota) {
            $providerPayload = $quota->provider_payload ?? [];
            $telegramData = $providerPayload['telegram'] ?? [];
            $executionMeta = $providerPayload['execution_meta'] ?? [];

            // Check inspection
            if (!is_array($telegramData) || !($telegramData['ok'] ?? false)) {
                return false;
            }

            // Check execution meta
            if (!is_array($executionMeta)) {
                return false;
            }

            // Check next_run_at
            $nextRunAt = $executionMeta['next_run_at'] ?? null;
            if ($nextRunAt) {
                try {
                    $nextRunAtCarbon = \Carbon\Carbon::parse($nextRunAt);
                    if ($nextRunAtCarbon->isFuture()) {
                        return false;
                    }
                } catch (\Throwable) {
                    // Invalid date, treat as eligible
                }
            }

            return true;
        })->take($batchSize);

        foreach ($quotas as $quota) {
            if ($generated >= $maxTasks) {
                break;
            }

            try {
                $task = $this->generateTaskForQuota($quota);
                if ($task) {
                    $generated++;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to generate task for quota', [
                    'quota_id' => $quota->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $generated;
    }

    /**
     * Generate a single task for a quota.
     *
     * @param ClientServiceQuota $quota
     * @return TelegramTask|null
     */
    private function generateTaskForQuota(ClientServiceQuota $quota): ?TelegramTask
    {
        $providerPayload = $quota->provider_payload ?? [];
        $executionMeta = $providerPayload['execution_meta'] ?? [];
        $telegramData = $providerPayload['telegram'] ?? [];

        if (!is_array($executionMeta) || !is_array($telegramData)) {
            return null;
        }

        // Check next_run_at
        $nextRunAt = $executionMeta['next_run_at'] ?? null;
        if ($nextRunAt) {
            $nextRunAtCarbon = \Carbon\Carbon::parse($nextRunAt);
            if ($nextRunAtCarbon->isFuture()) {
                return null;
            }
        }

        // Get action and policy
        $action = (string) ($executionMeta['action'] ?? 'subscribe');
        $policy = config("telegram.action_policies.{$action}", []);
        $dedupePerLink = (bool) ($policy['dedupe_per_link'] ?? true);

        // Get link data
        $parsed = is_array($telegramData['parsed'] ?? null) ? $telegramData['parsed'] : [];
        $linkHash = $this->dedupeService->normalizeAndHashLink($parsed);

        // Handle posts snapshot for last-N-post quotas
        $postId = $this->getPostIdForQuota($quota, $parsed, $action);
        if ($postId === false) {
            // Snapshot refresh failed or empty, skip this quota
            return null;
        }

        $service = $quota->service;
        $executor = $service ? $service->executor() : 'remote_provider';

        // Select account with reserve claim (manual => only accounts with mtproto_account_id)
        [$account, $stats] = $this->selectAccountWithReserveForQuota(
            $action,
            $quota,
            $linkHash,
            $dedupePerLink,
            $executor
        );

        if (!$account) {
            $errorMessage = $executor === 'local_mtproto'
                ? 'No local MTProto accounts available'
                : 'No available Telegram account (cooldown/cap/dedupe constraints)';
            $quota->update([
                'provider_last_error' => $errorMessage,
                'provider_last_error_at' => now(),
            ]);
            return null;
        }

        $templateKey = $service?->template_key;
        $policyKey = $service?->policyKey();
        $linkKind = $parsed['kind'] ?? null;
        $peerType = $telegramData['chat_type'] ?? null;
        $startParam = $parsed['start'] ?? null;

        $payload = $this->buildTaskPayload([
            'action' => $action,
            'link' => $quota->link,
            'link_hash' => $linkHash,
            'per_call' => 1,
            'executor' => $executor,
            'template_key' => $templateKey,
            'policy_key' => $policyKey,
            'link_kind' => $linkKind,
            'peer_type' => $peerType,
            'post_id' => $postId,
            'start_param' => $startParam,
            'meta' => $executionMeta,
            'parsed' => $parsed,
            'subject' => ['type' => 'quota', 'id' => $quota->id],
        ]);

        // Create task
        $task = TelegramTask::create([
            'order_id' => null, // Quotas don't have order_id
            'subject_type' => ClientServiceQuota::class,
            'subject_id' => $quota->id,
            'action' => $action,
            'link_hash' => $linkHash,
            'telegram_account_id' => $account->id,
            'provider_account_id' => $account->provider_account_id,
            'status' => TelegramTask::STATUS_QUEUED,
            'attempt' => 0,
            'payload' => $payload,
        ]);

        // Advance quota.next_run_at
        $intervalSeconds = (int) ($executionMeta['interval_seconds'] ?? 60);
        $executionMeta['next_run_at'] = now()->addSeconds($intervalSeconds)->toDateTimeString();
        $providerPayload['execution_meta'] = $executionMeta;
        $quota->update(['provider_payload' => $providerPayload]);

        Log::debug('Generated task for quota', [
            'task_id' => $task->id,
            'quota_id' => $quota->id,
            'account_id' => $account->id,
            'action' => $action,
            'post_id' => $postId,
        ]);

        return $task;
    }

    /**
     * Get post_id for quota task (handles posts snapshot for last-N-post quotas).
     *
     * @param ClientServiceQuota $quota
     * @param array $parsed
     * @param string $action
     * @return int|false Returns post_id or false if snapshot refresh failed/empty
     */
    private function getPostIdForQuota(ClientServiceQuota $quota, array $parsed, string $action): int|false
    {
        // For non-post actions, use post_id from parsed if available
        if (!in_array($action, ['view', 'react', 'comment'], true)) {
            return $parsed['post_id'] ?? 0;
        }

        // For post actions, check if we need posts snapshot
        $providerPayload = $quota->provider_payload ?? [];
        $telegramData = $providerPayload['telegram'] ?? [];
        $postsSnapshot = $telegramData['posts_snapshot'] ?? null;

        $snapshotTtl = (int) config('telegram.quota.posts_snapshot_ttl_hours', 24);
        $snapshotStale = false;

        if ($postsSnapshot) {
            $fetchedAt = $postsSnapshot['fetched_at'] ?? null;
            if ($fetchedAt) {
                try {
                    $fetchedAtCarbon = \Carbon\Carbon::parse($fetchedAt);
                    $snapshotStale = $fetchedAtCarbon->addHours($snapshotTtl)->isPast();
                } catch (\Throwable) {
                    $snapshotStale = true;
                }
            } else {
                $snapshotStale = true;
            }
        }

        // Refresh snapshot if missing or stale
        if (!$postsSnapshot || $snapshotStale) {
            $newSnapshot = $this->refreshPostsSnapshot($quota, $parsed);
            if ($newSnapshot === false) {
                // Refresh failed, set error and skip
                $quota->update([
                    'provider_last_error' => 'Failed to fetch posts snapshot for quota',
                    'provider_last_error_at' => now(),
                ]);
                return false;
            }

            $telegramData['posts_snapshot'] = $newSnapshot;
            $providerPayload['telegram'] = $telegramData;
            $quota->update(['provider_payload' => $providerPayload]);
            $postsSnapshot = $newSnapshot;
        }

        $postIds = $postsSnapshot['post_ids'] ?? [];
        if (empty($postIds)) {
            return false; // No posts available
        }

        // Get cursor and select next post_id
        $quotaCursor = $providerPayload['quota_cursor'] ?? ['post_index' => 0];
        $postIndex = (int) ($quotaCursor['post_index'] ?? 0);

        // Round-robin: if index >= count, wrap to 0
        if ($postIndex >= count($postIds)) {
            $postIndex = 0;
        }

        $postId = (int) $postIds[$postIndex];

        // Advance cursor
        $quotaCursor['post_index'] = ($postIndex + 1) % count($postIds);
        $providerPayload['quota_cursor'] = $quotaCursor;
        $quota->update(['provider_payload' => $providerPayload]);

        return $postId;
    }

    /**
     * Refresh posts snapshot for quota.
     *
     * @param ClientServiceQuota $quota
     * @param array $parsed
     * @return array|false Returns snapshot array or false on failure
     */
    private function refreshPostsSnapshot(ClientServiceQuota $quota, array $parsed): array|false
    {
        // TODO: Implement actual posts fetching via TelegramInspector or provider helper
        // For now, return false to indicate not implemented
        // In production, this should:
        // 1. Use TelegramInspector or provider API to fetch last N posts from channel
        // 2. Extract post_ids
        // 3. Return ['fetched_at' => now()->toDateTimeString(), 'post_ids' => [...]]

        Log::warning('Posts snapshot refresh not implemented', [
            'quota_id' => $quota->id,
            'link' => $quota->link,
        ]);

        return false;
    }

    /**
     * Select account with reserve claim for quota (reuses order logic).
     *
     * @param string $action
     * @param ClientServiceQuota $quota
     * @param string $linkHash
     * @param bool $dedupePerLink
     * @return array{TelegramAccount|null, array}
     */
    private function selectAccountWithReserveForQuota(
        string $action,
        ClientServiceQuota $quota,
        string $linkHash,
        bool $dedupePerLink,
        string $executor = 'remote_provider'
    ): array {
        // Reuse the same account selection logic as orders
        // We can't directly call selectAccountWithReserve because it expects Order
        // So we'll duplicate the logic here (or refactor to accept a generic subject)

        $batchSize = (int) config('telegram.account_selection.batch_size', 200);
        $maxScanLimit = (int) config('telegram.account_selection.max_scan_limit', 2000);

        $cursorKey = 'tg:acct_cursor:global';
        $startFromId = (int) (Redis::get($cursorKey) ?? 0);

        $account = null;
        $scanned = 0;
        $skippedCap = 0;
        $skippedCooldown = 0;
        $skippedDedupe = 0;
        $skippedLock = 0;

        $needsCap = in_array($action, ['subscribe', 'unsubscribe'], true);

        $tryCandidate = function ($candidate) use (
            &$account,
            &$scanned,
            &$skippedCap,
            &$skippedCooldown,
            &$skippedDedupe,
            &$skippedLock,
            $action,
            $linkHash,
            $dedupePerLink,
            $needsCap
        ): bool {
            $scanned++;

            // Reserve claim (only sets lock, no cap/cooldown consumption)
            $reserveResult = $this->claimService->reserveClaim(
                $candidate->id,
                $action,
                $linkHash,
                $dedupePerLink
            );

            if (!($reserveResult['success'] ?? false)) {
                $reason = (string) ($reserveResult['reason'] ?? 'unknown');

                if (in_array($reason, ['dedupe', 'dedupe_race', 'state_already_subscribed', 'state_not_subscribed'], true)) {
                    $skippedDedupe++;
                    return false;
                }

                if ($reason === 'locked') {
                    $skippedLock++;
                    return false;
                }

                $skippedDedupe++;
                return false;
            }

            $account = $candidate;
            return true;
        };

        $baseSelect = ['id', 'provider_account_id', 'phone', 'subscription_count', 'is_active'];

        // PASS 1: scan from cursor -> max
        TelegramAccount::query()
            ->select($baseSelect)
            ->where('is_active', true)
            ->whereColumn('subscription_count', '<', 'max_subscription_count')
            ->when($executor === 'local_mtproto', fn ($q) => $q->whereNotNull('mtproto_account_id'))
            ->where('id', '>', $startFromId)
            ->orderBy('id')
            ->chunkById($batchSize, function ($candidates) use (&$account, &$scanned, $tryCandidate, $maxScanLimit) {
                foreach ($candidates as $candidate) {
                    if ($scanned >= $maxScanLimit) {
                        return false;
                    }
                    if ($tryCandidate($candidate)) {
                        return false;
                    }
                }
                return true;
            });

        // PASS 2: wrap-around
        if (!$account && $startFromId > 0 && $scanned < $maxScanLimit) {
            TelegramAccount::query()
                ->select($baseSelect)
                ->where('is_active', true)
                ->whereColumn('subscription_count', '<', 'max_subscription_count')
                ->when($executor === 'local_mtproto', fn ($q) => $q->whereNotNull('mtproto_account_id'))
                ->where('id', '<=', $startFromId)
                ->orderBy('id')
                ->chunkById($batchSize, function ($candidates) use (&$account, &$scanned, $tryCandidate, $maxScanLimit) {
                    foreach ($candidates as $candidate) {
                        if ($scanned >= $maxScanLimit) {
                            return false;
                        }
                        if ($tryCandidate($candidate)) {
                            return false;
                        }
                    }
                    return true;
                });
        }

        // Update cursor
        if ($account) {
            Redis::set($cursorKey, (string) $account->id);
        }

        return [
            $account,
            [
                'cursor_start' => $startFromId,
                'scanned' => $scanned,
                'skipped_cap' => $skippedCap,
                'skipped_cooldown' => $skippedCooldown,
                'skipped_dedupe' => $skippedDedupe,
                'skipped_lock' => $skippedLock,
            ],
        ];
    }

    /**
     * Handle quota task report.
     *
     * @param TelegramTask $task
     * @param ClientServiceQuota $quota
     * @param TelegramAccount $account
     * @param string $state
     * @param bool $ok
     * @param string|null $error
     * @param int|null $retryAfter
     * @return array{ok: bool, error?: string}
     */
    private function handleQuotaTaskReport(
        TelegramTask $task,
        ClientServiceQuota $quota,
        TelegramAccount $account,
        string $state,
        bool $ok,
        ?string $error,
        ?int $retryAfter
    ): array {
        // Update task
        $task->update([
            'result' => [
                'state' => $state,
                'ok' => $ok,
                'error' => $error,
                'retry_after' => $retryAfter,
            ],
            'status' => $state === 'pending' ? TelegramTask::STATUS_PENDING : ($ok ? TelegramTask::STATUS_DONE : TelegramTask::STATUS_FAILED),
        ]);

        // Handle pending state
        if ($state === 'pending') {
            if ($retryAfter) {
                $task->update([
                    'status' => TelegramTask::STATUS_PENDING,
                    'leased_until' => now()->addSeconds(max(60, min(300, (int) $retryAfter))),
                ]);

                // Update quota next_run_at for retry
                $providerPayload = $quota->provider_payload ?? [];
                $executionMeta = $providerPayload['execution_meta'] ?? [];
                $executionMeta['next_run_at'] = now()->addSeconds(max(60, min(300, (int) $retryAfter)))->toDateTimeString();
                $providerPayload['execution_meta'] = $executionMeta;
                $quota->update(['provider_payload' => $providerPayload]);
            }
            return ['ok' => true];
        }

        // Handle success (done + ok)
        if ($ok && $state === 'done') {
            $action = $task->action;
            $providerPayload = $quota->provider_payload ?? [];
            $executionMeta = $providerPayload['execution_meta'] ?? [];
            $policy = config("telegram.action_policies.{$action}", []);
            $dedupePerLink = (bool) ($policy['dedupe_per_link'] ?? true);
            $needsCap = in_array($action, ['subscribe', 'unsubscribe'], true);

            // Commit claim: consume cooldown, cap, set state
            $commitResult = $this->claimService->commitClaim(
                $account->id,
                $action,
                $task->link_hash,
                $dedupePerLink,
                $needsCap
            );

            if (!($commitResult['success'] ?? false)) {
                Log::warning('Failed to commit claim for quota task', [
                    'task_id' => $task->id,
                    'quota_id' => $quota->id,
                    'account_id' => $account->id,
                    'reason' => $commitResult['reason'] ?? 'unknown',
                ]);
            }

            // Update quota counters (idempotent: check if already decremented)
            $payload = $task->payload;
            $perCall = (int) ($payload['per_call'] ?? 1);

            // Decrement quantity_left or orders_left
            if ($quota->quantity_left !== null) {
                // Use quantity_left, decrement by per_call
                $quota->quantity_left = max(0, ($quota->quantity_left ?? 0) - $perCall);
            } elseif ($quota->orders_left !== null) {
                // Use orders_left, decrement by 1
                $quota->orders_left = max(0, ($quota->orders_left ?? 0) - 1);
            }

            // Clear error on success
            $quota->provider_last_error = null;
            $quota->provider_last_error_at = null;

            // Update execution_meta.next_run_at (already advanced during generation, but update for consistency)
            $intervalSeconds = (int) ($executionMeta['interval_seconds'] ?? 60);
            $executionMeta['next_run_at'] = now()->addSeconds($intervalSeconds)->toDateTimeString();
            $providerPayload['execution_meta'] = $executionMeta;
            $quota->provider_payload = $providerPayload;

            $quota->save();

            $task->update(['status' => TelegramTask::STATUS_DONE]);

            Log::debug('Quota task completed successfully', [
                'task_id' => $task->id,
                'quota_id' => $quota->id,
                'action' => $action,
                'quantity_left' => $quota->quantity_left,
                'orders_left' => $quota->orders_left,
            ]);

            return ['ok' => true];
        }

        // Handle failure
        // Rollback reserve (release lock only)
        $action = $task->action;
        $this->claimService->rollbackReserve($account->id, $action);

        // Update quota error
        $quota->update([
            'provider_last_error' => $error ?? 'Provider task failed',
            'provider_last_error_at' => now(),
        ]);

        // Update execution_meta.next_run_at for retry
        $providerPayload = $quota->provider_payload ?? [];
        $executionMeta = $providerPayload['execution_meta'] ?? [];
        $retryDelay = $retryAfter ? max(60, min(300, (int) $retryAfter)) : 60;
        $executionMeta['next_run_at'] = now()->addSeconds($retryDelay)->toDateTimeString();
        $providerPayload['execution_meta'] = $executionMeta;
        $quota->update(['provider_payload' => $providerPayload]);

        $task->update(['status' => TelegramTask::STATUS_FAILED]);

        return ['ok' => true];
    }
}

