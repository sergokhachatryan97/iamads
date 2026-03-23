<?php

namespace App\Services\YouTube;

use App\Models\Order;
use App\Models\YouTubeAccountTargetState;
use App\Models\YouTubeTask;
use App\Services\ProviderActionLogService;
use App\Support\Performer\OrderDripfeedClaimHelper;
use App\Support\YouTube\YouTubeTargetNormalizer;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * YouTube performer claim: dynamic action from order (execution_meta.action), dripfeed-aware,
 * lightweight (view, comment, …) vs stateful subscribe (global target state).
 */
class YouTubeTaskClaimService
{
    private const LEASE_TTL_SECONDS = 180;

    /** Actions that use youtube_account_target_states (cross-order uniqueness). */
    private const LAST_CLAIMED_ORDER_CACHE_KEY = 'youtube:claim:last_order_id';
    public function __construct(
        private ProviderActionLogService $actionLogService
    ) {}

    public function claim(string $accountIdentity): ?array
    {
        $accountIdentity = trim($accountIdentity);
        if ($accountIdentity === '') {
            return null;
        }

        $lastClaimedOrderId = (int) cache()->get(self::LAST_CLAIMED_ORDER_CACHE_KEY, 0);
        $now = now();

        $ordersAfter = $this->baseEligibleOrdersQuery()
            ->where('id', '>', $lastClaimedOrderId)
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->filter(function (Order $order) use ($now) {
                $dueAt = $this->computeOrderDueAt($order);
                return $dueAt === null || $dueAt->lte($now);
            })
            ->values();

        foreach ($ordersAfter as $order) {

            $result = $this->tryClaimForOrder($order, $accountIdentity);

            if ($result !== null) {
                cache()->forever(self::LAST_CLAIMED_ORDER_CACHE_KEY, (int) $order->id);
                return $result;
            }
        }

        $ordersBefore = $this->baseEligibleOrdersQuery()
            ->when($lastClaimedOrderId > 0, function ($q) use ($lastClaimedOrderId) {
                $q->where('id', '<=', $lastClaimedOrderId);
            })
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->filter(function (Order $order) use ($now) {
                $dueAt = $this->computeOrderDueAt($order);
                return $dueAt === null || $dueAt->lte($now);
            })
            ->values();

        foreach ($ordersBefore as $order) {
            $result = $this->tryClaimForOrder($order, $accountIdentity);

            if ($result !== null) {
                cache()->forever(self::LAST_CLAIMED_ORDER_CACHE_KEY, (int) $order->id);
                return $result;
            }
        }

        return null;
    }

    private function baseEligibleOrdersQuery()
    {
        return Order::query()
            ->whereIn('status', [
                Order::STATUS_AWAITING,
                Order::STATUS_IN_PROGRESS,
                Order::STATUS_PENDING,
            ])
            ->where('remains', '>', 0)
            ->whereHas('service', function ($q) {
                $q->whereHas('category', function ($q2) {
                    $q2->where('link_driver', 'youtube');
                });
            });
    }

    private function resolveExecutionPlan(Order $order): array
    {
        return YouTubeExecutionPlanResolver::resolve($order);
    }


    private function stepsContainStatefulAction(array $steps): bool
    {
        return YouTubeExecutionPlanResolver::stepsContainSubscribe($steps);
    }

    /**
     * When is this order due for claiming (dripfeed or next_run_at). Align with Telegram computeSubscribeDueAt.
     */
    private function computeOrderDueAt(Order $order): ?Carbon
    {
        if ((bool) ($order->dripfeed_enabled ?? false)) {
            $v = $order->dripfeed_next_run_at ?? null;
            if (!$v) {
                return null;
            }
            try {
                return Carbon::parse($v);
            } catch (\Throwable) {
                return null;
            }
        }
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];
        $nextRunAt = $executionMeta['next_run_at'] ?? null;
        if (!$nextRunAt) {
            return null;
        }
        try {
            return Carbon::parse($nextRunAt);
        } catch (\Throwable) {
            return null;
        }
    }

    private function tryClaimForOrder(Order $order, string $accountIdentity): ?array
    {
        return DB::transaction(function () use ($order, $accountIdentity): ?array {
            $order = Order::query()
                ->where('id', $order->id)
                ->where('remains', '>', 0)
                ->whereHas('service', function ($q) {
                    $q->whereHas('category', function ($q2) {
                        $q2->where('link_driver', 'youtube');
                    });
                })
                ->lockForUpdate()
                ->with(['service', 'service.category'])
                ->first();

            if ($order === null) {
                return null;
            }

            $plan = $this->resolveExecutionPlan($order);
            $action = $plan['action'];
            $steps = $plan['steps'];
            $mode = $plan['mode'];
            $perCall = $plan['per_call'];

            $link = trim((string) ($order->link ?? ''));
            if ($link === '') {
                return null;
            }

            $linkHash = YouTubeTargetNormalizer::linkHash($link);

            if (!OrderDripfeedClaimHelper::canClaimTaskNow($order)) {
                return null;
            }
            $order->refresh();

            $inFlight = YouTubeTask::query()
                ->where('order_id', $order->id)
                ->whereIn('status', [YouTubeTask::STATUS_LEASED])
                ->count();

            $target = $order->target_quantity;
            if ((int) $order->delivered + $inFlight >= $target) {
                return null;
            }

            $providerPayload = $order->provider_payload ?? [];
            $youtube = $providerPayload['youtube'] ?? [];
            $parsed = $youtube['parsed'] ?? [];
            $targetHashForLog = $parsed['target_hash'] ?? $linkHash;

            // Unified step-based conflict: both single and combo use the same check.
            // If any step (action) is already active or performed for same account + same link, block.
            $actionNames = YouTubeExecutionPlanResolver::stepsToActionNames($steps);
            if ($this->hasStepConflict($accountIdentity, $linkHash, $targetHashForLog, $actionNames)) {
                return null;
            }
            $actionForLog = $mode === YouTubeExecutionPlanResolver::MODE_COMBO
                ? YouTubeExecutionPlanResolver::compositeActionForLog($steps)
                : $action;

            $targetType = null;
            $normalizedTarget = null;
            $targetHashForRow = null;

            $global = null;
            $globalRowCreated = false;
            $previousGlobalState = null;

            $statefulActionForTarget = $this->stepsContainStatefulAction($steps) ? 'subscribe' : null;
            if ($statefulActionForTarget !== null) {
                $norm = YouTubeTargetNormalizer::forSubscribeTarget($order);
                $targetType = $norm['target_type'];
                $normalizedTarget = $norm['normalized_target'];
                $targetHashForRow = $norm['target_hash'];

                $global = YouTubeAccountTargetState::query()
                    ->where('account_identity', $accountIdentity)
                    ->where('action', $statefulActionForTarget)
                    ->where('target_hash', $targetHashForRow)
                    ->lockForUpdate()
                    ->first();

                if ($global !== null) {
                    if (in_array($global->state, [
                        YouTubeAccountTargetState::STATE_IN_PROGRESS,
                        YouTubeAccountTargetState::STATE_SUBSCRIBED,
                    ], true)) {
                        return null;
                    }
                    $previousGlobalState = $global->state;
                    $global->update([
                        'state' => YouTubeAccountTargetState::STATE_IN_PROGRESS,
                        'last_error' => null,
                    ]);
                } else {
                    try {
                        $global = YouTubeAccountTargetState::create([
                            'account_identity' => $accountIdentity,
                            'action' => $statefulActionForTarget,
                            'target_type' => $targetType,
                            'normalized_target' => mb_substr($normalizedTarget, 0, 500),
                            'target_hash' => $targetHashForRow,
                            'state' => YouTubeAccountTargetState::STATE_IN_PROGRESS,
                        ]);
                        $globalRowCreated = true;
                    } catch (UniqueConstraintViolationException) {
                        return null;
                    }
                }
            }

            if (YouTubeTask::query()
                ->where('account_identity', $accountIdentity)
                ->where('order_id', $order->id)
                ->where('link_hash', $linkHash)
                ->where('action', $action)
                ->exists()) {
                $this->revertGlobalState($global, $globalRowCreated, $previousGlobalState);
                return null;
            }

            $leasedUntil = now()->addSeconds(self::LEASE_TTL_SECONDS);
            $payload = [
                'order_id' => $order->id,
                'per_call' => $perCall,
                'action' => $action,
            ];
            if ($mode === YouTubeExecutionPlanResolver::MODE_COMBO) {
                $payload['mode'] = 'combo';
                $payload['steps'] = $steps;
            }
            // comment_custom: works like default comment — multiple comments (one per line), pick by index
            if (in_array('comment_custom', $steps, true) && !empty(trim((string) ($order->comment_text ?? '')))) {
                $comments = array_values(array_filter(array_map('trim', explode("\n", (string) $order->comment_text))));
                $index = (int) $order->delivered + $inFlight;
                $commentForPayload = null;
                if (!empty($comments)) {
                    $commentForPayload = $comments[$index % count($comments)];
                } else {
                    $commentForPayload = trim((string) $order->comment_text);
                }
                if ($commentForPayload !== '') {
                    $payload['comment_text'] = $commentForPayload;
                }
            }
            if ($targetHashForRow !== null) {
                $payload['target_hash'] = $targetHashForRow;
                $payload['normalized_target'] = $normalizedTarget;
            }
            // For combo: view/react use video target, subscribe uses channel. Store video hash for recording.
            if ($mode === YouTubeExecutionPlanResolver::MODE_COMBO) {
                $payload['video_target_hash'] = $targetHashForLog;
            }

            $taskTargetHash = $targetHashForRow ?? $targetHashForLog;
            try {
                $task = YouTubeTask::create([
                    'order_id' => $order->id,
                    'account_identity' => $accountIdentity,
                    'action' => $action,
                    'link' => $link,
                    'link_hash' => $linkHash,
                    'target_type' => $targetType,
                    'normalized_target' => $normalizedTarget !== null ? mb_substr($normalizedTarget, 0, 500) : null,
                    'target_hash' => $taskTargetHash,
                    'status' => YouTubeTask::STATUS_LEASED,
                    'leased_until' => $leasedUntil,
                    'payload' => $payload,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                $this->revertGlobalState($global, $globalRowCreated, $previousGlobalState);
                Log::debug('YouTube task claim duplicate', [
                    'account_identity' => $accountIdentity,
                    'order_id' => $order->id,
                    'action' => $action,
                ]);
                return null;
            }

            if ($global !== null) {
                $global->update(['last_task_id' => $task->id]);
            }

            OrderDripfeedClaimHelper::afterTaskClaimed($order);
            $order->update(['status' => Order::STATUS_IN_PROGRESS]);

            Log::debug('YouTube task claimed', [
                'task_id' => $task->id,
                'order_id' => $order->id,
                'action' => $action,
            ]);

            $service = $order->service;
            $category = $service?->category;

            $commentTextForTask = null;
            if ($mode === YouTubeExecutionPlanResolver::MODE_COMBO) {
                if (in_array('comment_custom', $steps, true) && !empty(trim((string) ($order->comment_text ?? '')))) {
                    $comments = array_values(array_filter(array_map('trim', explode("\n", (string) $order->comment_text))));
                    $index = (int) $order->delivered + $inFlight;
                    if (!empty($comments)) {
                        $commentTextForTask = $comments[$index % count($comments)];
                    } else {
                        $commentTextForTask = trim((string) $order->comment_text);
                    }
                }
            } elseif ($action === 'comment' && !empty(trim((string) ($order->comment_text ?? '')))) {
                $comments = array_values(array_filter(array_map('trim', explode("\n", (string) $order->comment_text))));
                $index = (int) $order->delivered + $inFlight;
                if ($index < count($comments)) {
                    $commentTextForTask = $comments[$index];
                }
            }

            $serviceDescription = $service?->description_for_performer ?? '';

            if ($commentTextForTask !== null && trim((string) $commentTextForTask) !== '') {
                $serviceDescription .= ($serviceDescription !== '' ? "\n" : '')
                    . trim((string) $commentTextForTask);
            }

            $result = [
                'task_id' => $task->id,
                'link' => $link,
                'link_hash' => $linkHash,
                'action' => $action,
                'order_id' => (int) $order->id,
                'target' => $targetType !== null ? [
                    'type' => $targetType,
                    'normalized' => $normalizedTarget,
                    'hash' => $targetHashForRow,
                ] : null,
                'order' => [
                    'id' => (string) $order->id,
                    'quantity' => $order->quantity,
                    'delivered' => (int) $order->delivered,
                    'remains' => (int) $order->remains,
                    'target_quantity' => $order->target_quantity,
                    'dripfeed_enabled' => (bool) ($order->dripfeed_enabled ?? false),
                ],
                'service' => [
                    'id' => $service?->id,
                    'name' => $service?->name ?? '',
                    'description' => $serviceDescription ?? '',
                    'service_description' => $serviceDescription,
                ],
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name ?? '',
                ] : null,
            ];
            if ($mode === YouTubeExecutionPlanResolver::MODE_COMBO) {
                $result['mode'] = 'combo';
                $result['steps'] = $steps;
            }
            if ($commentTextForTask !== null && $commentTextForTask !== '') {
                $result['comment_text'] = $commentTextForTask;
            }
            return $result;
        });
    }

    /**
     * Unified step-based conflict: any requested action already active or performed
     * for the same account_identity + link_hash blocks the claim.
     * Works for both single-action and combo orders.
     *
     * @param  array<string>  $actionNames  Normalized action names (subscribe, view, react, comment)
     */
    private function hasStepConflict(
        string $accountIdentity,
        string $linkHash,
        string $videoTargetHash,
        array $actionNames
    ): bool {
        $activeStatuses = [YouTubeTask::STATUS_LEASED, YouTubeTask::STATUS_PENDING];

        // 1. Active tasks: single tasks with overlapping action, or combo tasks with overlapping steps
        $activeTasks = YouTubeTask::query()
            ->where('account_identity', $accountIdentity)
            ->where('link_hash', $linkHash)
            ->whereIn('status', $activeStatuses)
            ->get(['id', 'action', 'payload']);

        foreach ($activeTasks as $task) {
            if (in_array($task->action, $actionNames, true)) {
                return true; // Single task with same action
            }
            if ($task->action === 'combo') {
                $payload = $task->payload;
                $steps = is_array($payload) ? ($payload['steps'] ?? []) : [];
                $existingNames = YouTubeExecutionPlanResolver::stepsToActionNames($steps);
                if (array_intersect($actionNames, $existingNames) !== []) {
                    return true; // Combo overlaps with our actions
                }
            }
        }

        // 2. Performed actions: any requested step already done for this account + same video target
        foreach ($actionNames as $actionName) {
            if ($this->actionLogService->hasPerformed(
                ProviderActionLogService::PROVIDER_YOUTUBE,
                $accountIdentity,
                $videoTargetHash,
                $actionName
            )) {
                return true;
            }
        }

        // 3. Composite combo (exact combo already done; only when multiple actions)
        if (count($actionNames) > 1) {
            foreach (['like', 'react'] as $reactStep) {
                $stepNames = [];
                foreach ($actionNames as $name) {
                    $stepNames[] = ($name === 'react' || $name === 'like') ? $reactStep : $name;
                }
                $compositeAction = YouTubeExecutionPlanResolver::compositeActionForLog($stepNames);
                if ($this->actionLogService->hasPerformed(
                    ProviderActionLogService::PROVIDER_YOUTUBE,
                    $accountIdentity,
                    $videoTargetHash,
                    $compositeAction
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    private function revertGlobalState(
        ?YouTubeAccountTargetState $global,
        bool $globalRowCreated,
        ?string $previousGlobalState
    ): void {
        if ($global === null) {
            return;
        }
        if ($globalRowCreated) {
            $global->delete();
        } elseif ($previousGlobalState !== null) {
            $global->update(['state' => $previousGlobalState]);
        }
    }
}
