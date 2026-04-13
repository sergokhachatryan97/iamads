<?php

namespace App\Services\Max;

use App\Models\MaxTask;
use App\Models\Order;
use App\Support\Performer\OrderDripfeedClaimHelper;
use Illuminate\Support\Facades\Log;

/**
 * Max Messenger task reporting: mark done/failed, update order delivery.
 */
class MaxTaskService
{
    public function reportTaskResult(string $taskId, array $result): array
    {
        $task = MaxTask::query()->find($taskId);

        if (! $task) {
            return ['ok' => false, 'error' => 'Task not found'];
        }

        if ($task->isFinalized()) {
            return ['ok' => true];
        }

        $state = (string) ($result['state'] ?? 'done');
        $ok = (bool) ($result['ok'] ?? false);
        $error = $result['error'] ?? null;

        $task->update([
            'result' => $result,
            'status' => $state === 'pending' ? MaxTask::STATUS_PENDING : ($ok ? MaxTask::STATUS_DONE : MaxTask::STATUS_FAILED),
        ]);

        if ($state === 'pending') {
            return ['ok' => true];
        }

        $order = $task->order;
        if (! $order) {
            return ['ok' => true];
        }

        if ($order->status === Order::STATUS_COMPLETED) {
            return ['ok' => true];
        }

        if ($ok && $state === 'done') {
            $perCall = max(1, (int) ($task->payload['per_call'] ?? 1));
            $currentRemains = (int) $order->remains;
            $deduct = min($perCall, $currentRemains);
            $newDelivered = (int) $order->delivered + $deduct;
            $target = $order->target_quantity;

            $orderUpdates = [
                'remains' => $newDelivered >= $target ? 0 : max(0, $target - $newDelivered),
                'delivered' => $newDelivered,
                'provider_last_error' => null,
                'provider_last_error_at' => null,
            ];

            if ($newDelivered >= $target) {
                $orderUpdates['status'] = Order::STATUS_COMPLETED;
                $orderUpdates['completed_at'] = $order->completed_at ?? now();
            }

            // Dripfeed tracking
            if ((bool) ($order->dripfeed_enabled ?? false)) {
                $perRunQty = (int) ($order->dripfeed_quantity ?? 0);
                $deliveredInRun = (int) ($order->dripfeed_delivered_in_run ?? 0) + $perCall;
                $runIndex = (int) ($order->dripfeed_run_index ?? 0);
                $runsTotal = (int) ($order->dripfeed_runs_total ?? 0);
                $intervalMinutes = max(1, (int) ($order->dripfeed_interval_minutes ?? 60));

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

            // Update next_run_at for pacing
            $this->updateNextRunAt($order, $orderUpdates);

            $order->update($orderUpdates);
        } else {
            OrderDripfeedClaimHelper::rollbackClaimedUnit($order);

            $failUpdates = [
                'provider_last_error' => $error ?? 'Task failed',
                'provider_last_error_at' => now(),
            ];

            // Still respect pacing after failures
            $this->updateNextRunAt($order, $failUpdates);

            $order->update($failUpdates);
        }

        return ['ok' => true];
    }

    /**
     * Update next_run_at in execution_meta to maintain pacing between steps.
     */
    private function updateNextRunAt(Order $order, array &$orderUpdates): void
    {
        $providerPayload = $order->provider_payload ?? [];
        if (! is_array($providerPayload)) {
            return;
        }

        $executionMeta = $providerPayload['execution_meta'] ?? [];
        if (! is_array($executionMeta)) {
            return;
        }

        $intervalSeconds = (int) ($executionMeta['interval_seconds'] ?? 30);
        $executionMeta['next_run_at'] = now()->addSeconds(max(1, $intervalSeconds))->toDateTimeString();

        $providerPayload['execution_meta'] = $executionMeta;
        $orderUpdates['provider_payload'] = $providerPayload;
    }

    public function markIgnored(string $taskId, ?string $error = null): array
    {
        $task = MaxTask::query()->find($taskId);
        if (! $task) {
            return ['ok' => false, 'error' => 'Task not found'];
        }

        if ($task->isFinalized()) {
            return ['ok' => true];
        }

        $errorMessage = $error ?? 'Ignored';

        $order = $task->order;
        if ($order) {
            OrderDripfeedClaimHelper::rollbackClaimedUnit($order);
            $order->update([
                'provider_last_error' => $errorMessage,
                'provider_last_error_at' => now(),
            ]);
        }

        $task->update([
            'status' => MaxTask::STATUS_FAILED,
            'result' => array_merge($task->result ?? [], ['state' => 'failed', 'ok' => false, 'ignored' => true, 'error' => $errorMessage]),
        ]);

        return ['ok' => true];
    }
}
