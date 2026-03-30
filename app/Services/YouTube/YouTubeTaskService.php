<?php

namespace App\Services\YouTube;

use App\Models\Order;
use App\Models\YouTubeAccountTargetState;
use App\Models\YouTubeTask;
use App\Services\ProviderActionLogService;
use App\Support\Performer\OrderDripfeedClaimHelper;
use Illuminate\Support\Facades\Log;

/**
 * YouTube performer report: mark task done/failed; update order and global target state for subscribe.
 */
class YouTubeTaskService
{
    private const STATEFUL_ACTIONS = ['subscribe'];

    public function __construct(
        private ProviderActionLogService $actionLogService
    ) {}

    public function reportTaskResult(string $taskId, array $result): array
    {
        $task = YouTubeTask::query()->find($taskId);

        if (!$task) {
            return ['ok' => false, 'error' => 'Task not found'];
        }

        if ($task->isFinalized()) {
            Log::info('YouTube task already finalized', ['task_id' => $taskId, 'status' => $task->status]);
            return ['ok' => true];
        }

        $state = (string) ($result['state'] ?? 'done');
        $ok = (bool) ($result['ok'] ?? false);
        $error = $result['error'] ?? null;

        $task->update([
            'result' => $result,
            'status' => $state === 'pending' ? YouTubeTask::STATUS_PENDING : ($ok ? YouTubeTask::STATUS_DONE : YouTubeTask::STATUS_FAILED),
        ]);

        if ($state === 'pending') {
            return ['ok' => true];
        }

        $order = $task->order;
        if ($order->status === Order::STATUS_COMPLETED) {
            return ['ok' => true];
        }
        if (!$order) {
            $this->updateGlobalStateForTask($task, $ok, $error);
            return ['ok' => true];
        }

        if ($ok && $state === 'done') {
            // TODO: action log recording disabled — uniqueness checks are off in claim
            // $targetHash = $task->target_hash ?? $task->link_hash;
            // $actionForLog = $task->action;
            // if ($task->action === 'combo') {
            //     $steps = $task->payload['steps'] ?? [];
            //     $actionForLog = !empty($steps)
            //         ? YouTubeExecutionPlanResolver::compositeActionForLog($steps)
            //         : 'combo';
            //     $videoTargetHash = $task->payload['video_target_hash'] ?? $targetHash;
            //     $onePerAccountActions = YouTubeExecutionPlanResolver::onePerAccountSteps($steps);
            //     foreach ($onePerAccountActions as $individualAction) {
            //         $this->actionLogService->recordPerformed(
            //             ProviderActionLogService::PROVIDER_YOUTUBE,
            //             $task->account_identity,
            //             $videoTargetHash,
            //             $individualAction
            //         );
            //     }
            //     if (YouTubeExecutionPlanResolver::stepsContainSubscribe($steps)) {
            //         $hashForSubscribe = $videoTargetHash ?: $targetHash;
            //         if ($hashForSubscribe) {
            //             $this->actionLogService->recordPerformed(
            //                 ProviderActionLogService::PROVIDER_YOUTUBE,
            //                 $task->account_identity,
            //                 $hashForSubscribe,
            //                 'subscribe'
            //             );
            //         }
            //     }
            // }
            // $this->actionLogService->recordPerformed(
            //     ProviderActionLogService::PROVIDER_YOUTUBE,
            //     $task->account_identity,
            //     $targetHash,
            //     $actionForLog
            // );

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

            $this->applyDripfeedCompletionOnReport($order, $perCall, $orderUpdates);
            $order->update($orderUpdates);

            $task->update(['status' => YouTubeTask::STATUS_DONE]);
            // TODO: global state update disabled
            // $this->updateGlobalStateForTask($task, true, null);
        } else {
            OrderDripfeedClaimHelper::rollbackClaimedUnit($order);
            $order->update([
                'provider_last_error' => $error ?? 'Task failed',
                'provider_last_error_at' => now(),
            ]);
            $task->update(['status' => YouTubeTask::STATUS_FAILED]);
            // TODO: global state update disabled
            // $this->updateGlobalStateForTask($task, false, $error);
        }

        return ['ok' => true];
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

    private function updateGlobalStateForTask(YouTubeTask $task, bool $ok, ?string $error): void
    {
        $steps = $task->payload['steps'] ?? [];
        $hasSubscribe = $task->action === 'combo'
            ? YouTubeExecutionPlanResolver::stepsContainSubscribe($steps)
            : in_array(strtolower($task->action ?? ''), self::STATEFUL_ACTIONS, true);
        if (!$hasSubscribe) {
            return;
        }
        $targetHash = $task->target_hash ?? $task->payload['target_hash'] ?? null;
        if ($targetHash === null || ($task->account_identity ?? '') === '') {
            return;
        }

        $statefulAction = $task->action === 'combo' ? 'subscribe' : $task->action;
        $global = YouTubeAccountTargetState::query()
            ->where('account_identity', $task->account_identity)
            ->where('action', $statefulAction)
            ->where('target_hash', $targetHash)
            ->first();

        if ($global === null) {
            return;
        }

        $ignored = (bool) ($task->result['ignored'] ?? false);
        if ($ok) {
            $global->update([
                'state' => YouTubeAccountTargetState::STATE_SUBSCRIBED,
                'last_task_id' => $task->id,
                'last_error' => null,
            ]);
        } elseif ($ignored) {
            $global->update([
                'state' => YouTubeAccountTargetState::STATE_IGNORED,
                'last_task_id' => $task->id,
                'last_error' => 'Ignored',
            ]);
        } else {
            $global->update([
                'state' => YouTubeAccountTargetState::STATE_FAILED,
                'last_task_id' => $task->id,
                'last_error' => $error ?? 'Task failed',
            ]);
        }
    }

    /**
     * Mark task as ignored (performer skipped). Sets global state to IGNORED for subscribe.
     */
    public function markIgnored(string $taskId): array
    {
        $task = YouTubeTask::query()->find($taskId);
        if (!$task) {
            return ['ok' => false, 'error' => 'Task not found'];
        }
        if ($task->isFinalized()) {
            return ['ok' => true];
        }

        $order = $task->order;
        if ($order) {
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

        // TODO: global state update disabled — uniqueness checks are off in claim
        // $targetHash = $task->target_hash ?? $task->payload['target_hash'] ?? null;
        // $steps = $task->payload['steps'] ?? [];
        // $hasSubscribe = $task->action === 'combo'
        //     ? YouTubeExecutionPlanResolver::stepsContainSubscribe($steps)
        //     : in_array(strtolower($task->action ?? ''), self::STATEFUL_ACTIONS, true);
        // if ($targetHash !== null && $hasSubscribe) {
        //     YouTubeAccountTargetState::query()
        //         ->where('account_identity', $task->account_identity)
        //         ->where('action', 'subscribe')
        //         ->where('target_hash', $targetHash)
        //         ->update([
        //             'state' => YouTubeAccountTargetState::STATE_IGNORED,
        //             'last_task_id' => $task->id,
        //             'last_error' => 'Ignored',
        //         ]);
        // }

        return ['ok' => true];
    }
}
