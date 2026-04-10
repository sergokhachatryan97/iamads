<?php

namespace App\Services\YouTube;

use App\Models\Order;
use App\Models\YouTubeTask;
use App\Services\ProviderActionLogService;
use App\Support\Performer\OrderDripfeedClaimHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * YouTube performer report: mark task done/failed and update order counters.
 */
class YouTubeTaskService
{
    public function __construct(
        private ProviderActionLogService $actionLogService
    ) {}

    public function reportTaskResult(string $taskId, array $result): array
    {
        // Cheap pre-check outside the transaction so duplicate reports don't take locks.
        $existing = YouTubeTask::query()->find($taskId);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Task not found'];
        }
        if ($existing->isFinalized()) {
            Log::info('YouTube task already finalized', ['task_id' => $taskId, 'status' => $existing->status]);
            return ['ok' => true];
        }

        $ok = (bool) ($result['ok'] ?? false);
        $error = $result['error'] ?? null;

        return DB::transaction(function () use ($taskId, $result, $ok, $error): array {
            // Re-fetch the task under lock so two concurrent reports for the same task serialize.
            $task = YouTubeTask::query()
                ->where('id', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return ['ok' => false, 'error' => 'Task not found'];
            }
            if ($task->isFinalized()) {
                return ['ok' => true];
            }

            // Lock the order BEFORE reading delivered/remains so two concurrent reports
            // for different tasks of the same order can't both increment past the cap.
            $order = $task->order_id
                ? Order::query()->where('id', $task->order_id)->lockForUpdate()->first()
                : null;

            // Mark the task with its incoming result while locked.
            $task->update([
                'result' => $result,
                'status' => $ok ? YouTubeTask::STATUS_DONE : YouTubeTask::STATUS_FAILED,
            ]);

            if (!$order) {
                return ['ok' => true];
            }
            if ($order->status === Order::STATUS_COMPLETED) {
                return ['ok' => true];
            }

            if ($ok) {
                $this->applySuccess($task, $order);
            } else {
                $this->applyFailure($task, $order, $error);
            }

            return ['ok' => true];
        });
    }

    /**
     * Successful report: record uniqueness in provider_action_logs and increment order counters.
     * Caller must hold a lock on $order.
     */
    private function applySuccess(YouTubeTask $task, Order $order): void
    {
        // Record uniqueness so future claims for this account+target+action are blocked
        // by hasStepConflict() in YouTubeTaskClaimService.
        $targetHash = $task->target_hash ?? $task->link_hash;
        if ($targetHash !== null && ($task->account_identity ?? '') !== '') {
            if ($task->action === 'combo') {
                $steps = $task->payload['steps'] ?? [];
                $videoTargetHash = $task->payload['video_target_hash'] ?? $targetHash;
                $stepActions = YouTubeExecutionPlanResolver::stepsToActionNames($steps);

                foreach ($stepActions as $individualAction) {
                    $this->actionLogService->recordPerformed(
                        ProviderActionLogService::PROVIDER_YOUTUBE,
                        $task->account_identity,
                        $videoTargetHash,
                        $individualAction
                    );
                }

                $compositeAction = !empty($stepActions)
                    ? YouTubeExecutionPlanResolver::compositeActionForLog($stepActions)
                    : 'combo';
                $this->actionLogService->recordPerformed(
                    ProviderActionLogService::PROVIDER_YOUTUBE,
                    $task->account_identity,
                    $videoTargetHash,
                    $compositeAction
                );
            } else {
                $this->actionLogService->recordPerformed(
                    ProviderActionLogService::PROVIDER_YOUTUBE,
                    $task->account_identity,
                    $targetHash,
                    $task->action
                );
            }
        }

        $perCall = (int) ($task->payload['per_call'] ?? 1);
        $watchTimeMeta = $task->payload['watch_time'] ?? null;

        if ($watchTimeMeta !== null && $perCall === 0) {
            // Watch-time order: delivered = doneCount / tasksPerUnit (recomputed under lock).
            $tasksPerUnit = max(1, (int) ($watchTimeMeta['tasks_per_unit'] ?? 1));

            $doneCount = YouTubeTask::query()
                ->where('order_id', $order->id)
                ->where('status', YouTubeTask::STATUS_DONE)
                ->count();

            $target = $order->target_quantity;
            $newDelivered = min((int) floor($doneCount / $tasksPerUnit), $target);

            $orderUpdates = [
                'delivered' => $newDelivered,
                'remains' => max(0, $target - $newDelivered),
                'provider_last_error' => null,
                'provider_last_error_at' => null,
            ];
            if ($newDelivered >= $target) {
                $orderUpdates['status'] = Order::STATUS_COMPLETED;
                $orderUpdates['completed_at'] = $order->completed_at ?? now();
            }

            $order->update($orderUpdates);

            return;
        }

        // Standard order: increment delivered by per_call.
        $perCall = max(1, $perCall);
        $target = (int) $order->target_quantity;
        $currentDelivered = (int) $order->delivered;
        $headroom = max(0, $target - $currentDelivered);
        $deduct = min($perCall, $headroom);
        $newDelivered = $currentDelivered + $deduct;

        $orderUpdates = [
            'remains' => max(0, $target - $newDelivered),
            'delivered' => $newDelivered,
            'provider_last_error' => null,
            'provider_last_error_at' => null,
        ];
        if ($newDelivered >= $target) {
            $orderUpdates['status'] = Order::STATUS_COMPLETED;
            $orderUpdates['completed_at'] = $order->completed_at ?? now();
        }

        $this->applyDripfeedCompletionOnReport($order, $perCall, $orderUpdates);
        $order->update($orderUpdates);
    }

    /**
     * Failed report: roll back the dripfeed slot the claim took, surface the error.
     * Caller must hold a lock on $order.
     */
    private function applyFailure(YouTubeTask $task, Order $order, ?string $error): void
    {
        OrderDripfeedClaimHelper::rollbackClaimedUnit($order);
        $order->update([
            'provider_last_error' => $error ?? 'Task failed',
            'provider_last_error_at' => now(),
        ]);
    }

    /**
     * Apply dripfeed completion when task is reported done (align with Telegram / OrderDripfeedClaimHelper).
     */
    private function applyDripfeedCompletionOnReport(Order $order, int $perCall, array &$orderUpdates): void
    {
        if (!(bool) ($order->dripfeed_enabled ?? false)) {
            return;
        }
        $perRunQty = (int) ($order->dripfeed_quantity ?? 0);
        $deliveredInRun = (int) ($order->dripfeed_delivered_in_run ?? 0);
        $runIndex = (int) ($order->dripfeed_run_index ?? 0);
        $runsTotal = (int) ($order->dripfeed_runs_total ?? 0);
        $intervalMinutes = (int) ($order->dripfeed_interval_minutes ?? 0);
        if ($intervalMinutes <= 0) {
            $intervalMinutes = 60;
        }

        $deliveredInRun += $perCall;
        $orderUpdates['dripfeed_delivered_in_run'] = $deliveredInRun;

        if ($perRunQty > 0 && $deliveredInRun >= $perRunQty) {
            $orderUpdates['dripfeed_run_index'] = $runIndex + 1;
            $orderUpdates['dripfeed_delivered_in_run'] = 0;
            $orderUpdates['dripfeed_next_run_at'] = now()->addMinutes($intervalMinutes);
            if ($runsTotal > 0 && ($runIndex + 1) >= $runsTotal) {
                $orderUpdates['dripfeed_enabled'] = false;
            }
        }
    }

    /**
     * Mark task as ignored (performer skipped).
     */
    public function markIgnored(string $taskId): array
    {
        $existing = YouTubeTask::query()->find($taskId);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Task not found'];
        }
        if ($existing->isFinalized()) {
            return ['ok' => true];
        }

        return DB::transaction(function () use ($taskId): array {
            $task = YouTubeTask::query()
                ->where('id', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return ['ok' => false, 'error' => 'Task not found'];
            }
            if ($task->isFinalized()) {
                return ['ok' => true];
            }

            $order = $task->order_id
                ? Order::query()->where('id', $task->order_id)->lockForUpdate()->first()
                : null;

            if ($order && $order->status !== Order::STATUS_COMPLETED) {
                OrderDripfeedClaimHelper::rollbackClaimedUnit($order);
                $order->update([
                    'provider_last_error' => 'Ignored',
                    'provider_last_error_at' => now(),
                ]);
            }

            $task->update([
                'status' => YouTubeTask::STATUS_FAILED,
                'result' => array_merge($task->result ?? [], ['state' => 'failed', 'ok' => false, 'ignored' => true]),
            ]);

            return ['ok' => true];
        });
    }
}
