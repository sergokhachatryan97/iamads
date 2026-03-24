<?php

namespace App\Services\App;

use App\Models\AppTask;
use App\Models\Order;
use App\Services\ProviderActionLogService;
use App\Support\Performer\OrderDripfeedClaimHelper;
use Illuminate\Support\Facades\Log;

/**
 * App performer report: mark task done/failed; update order.
 */
class AppTaskService
{
    public function __construct(
        private ProviderActionLogService $actionLogService
    ) {}

    public function reportTaskResult(string $taskId, array $result): array
    {
        $task = AppTask::query()->find($taskId);

        if (!$task) {
            return ['ok' => false, 'error' => 'Task not found'];
        }

        if ($task->isFinalized()) {
            Log::info('App task already finalized', ['task_id' => $taskId, 'status' => $task->status]);
            return ['ok' => true];
        }

        $state = (string) ($result['state'] ?? 'done');
        $ok = (bool) ($result['ok'] ?? false);
        $error = $result['error'] ?? null;

        $task->update([
            'result' => $result,
            'status' => $state === 'pending' ? AppTask::STATUS_PENDING : ($ok ? AppTask::STATUS_DONE : AppTask::STATUS_FAILED),
        ]);

        if ($state === 'pending') {
            return ['ok' => true];
        }

        $order = $task->order;
        if (!$order) {
            return ['ok' => true];
        }

        if ($ok && $state === 'done') {
            $targetHash = $task->target_hash ?? $task->link_hash;
            $actionForLog = $task->action;
            $payload = $task->payload ?? [];
            $steps = $payload['steps'] ?? [$task->action];

            if (count($steps) > 1) {
                $actionForLog = AppExecutionPlanResolver::compositeActionForLog($steps);
            }

            $this->actionLogService->recordPerformed(
                ProviderActionLogService::PROVIDER_APP,
                $task->account_identity,
                $targetHash,
                $actionForLog
            );

            $perCall = max(1, (int) ($payload['per_call'] ?? 1));
            $currentRemains = (int) $order->remains;
            $deduct = min($perCall, $currentRemains);
            $newDelivered = (int) $order->delivered + $deduct;
            $targetQty = $order->target_quantity;

            $orderUpdates = [
                'remains' => $newDelivered >= $targetQty ? 0 : max(0, $targetQty - $newDelivered),
                'delivered' => $newDelivered,
                'provider_last_error' => null,
                'provider_last_error_at' => null,
            ];
            if ($newDelivered >= $targetQty) {
                $orderUpdates['status'] = Order::STATUS_COMPLETED;
                $orderUpdates['completed_at'] = $order->completed_at ?? now();
            }

            $this->applyDripfeedCompletionOnReport($order, $perCall, $orderUpdates);
            $order->update($orderUpdates);

            $task->update(['status' => AppTask::STATUS_DONE]);
        } else {
            OrderDripfeedClaimHelper::rollbackClaimedUnit($order);
            $order->update([
                'provider_last_error' => $error ?? 'Task failed',
                'provider_last_error_at' => now(),
            ]);
            $task->update(['status' => AppTask::STATUS_FAILED]);
        }

        return ['ok' => true];
    }

    private function applyDripfeedCompletionOnReport(Order $order, int $perCall, array &$orderUpdates): void
    {
        if (!(bool) ($order->dripfeed_enabled ?? false)) {
            return;
        }
        $perRunQty = (int) ($order->dripfeed_quantity ?? 0);
        $deliveredInRun = (int) ($order->dripfeed_delivered_in_run ?? 0);
        $runIndex = (int) ($order->dripfeed_run_index ?? 0);
        $runsTotal = (int) ($order->dripfeed_runs_total ?? 0);
        $intervalMinutes = (int) ($order->dripfeed_interval_minutes ?? 60);

        $deliveredInRun += $perCall;
        $orderUpdates['dripfeed_delivered_in_run'] = $deliveredInRun;

        if ($perRunQty > 0 && $deliveredInRun >= $perRunQty) {
            $orderUpdates['dripfeed_run_index'] = $runIndex + 1;
            $orderUpdates['dripfeed_delivered_in_run'] = 0;
            if ($runIndex + 1 < $runsTotal) {
                $orderUpdates['dripfeed_next_run_at'] = now()->addMinutes($intervalMinutes);
            }
        }
    }
}
