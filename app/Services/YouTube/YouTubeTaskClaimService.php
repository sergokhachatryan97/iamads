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
    private const STATEFUL_ACTIONS = ['subscribe'];

    public function __construct(
        private ProviderActionLogService $actionLogService
    ) {}

    public function claim(string $accountIdentity): ?array
    {
        $accountIdentity = trim($accountIdentity);
        if ($accountIdentity === '') {
            return null;
        }

        $orders = Order::query()
            ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PENDING])
            ->where('remains', '>', 0)
            ->whereHas('service', function ($q) {
                $q->whereHas('category', function ($q2) {
                    $q2->where('link_driver', 'youtube');
                });
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        $now = now();
        $dueOrders = $orders
            ->filter(function (Order $o) use ($now) {
                $dueAt = $this->computeOrderDueAt($o);
                return $dueAt === null || $dueAt->lte($now);
            })
            ->sortBy(function (Order $o) {
                $dueAt = $this->computeOrderDueAt($o);
                return $dueAt ? $dueAt->getTimestamp() : 0;
            });
        foreach ($dueOrders as $order) {
            $result = $this->tryClaimForOrder($order, $accountIdentity);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    private function resolveAction(Order $order): string
    {
        $payload = $order->provider_payload ?? [];
        $meta = is_array($payload['execution_meta'] ?? null) ? $payload['execution_meta'] : [];
        $action = strtolower(trim((string) ($meta['action'] ?? 'view')));
        return $action !== '' ? $action : 'view';
    }

    private function isStatefulAction(string $action): bool
    {
        return in_array(strtolower($action), self::STATEFUL_ACTIONS, true);
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

            $action = $this->resolveAction($order);
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
                ->whereIn('status', [YouTubeTask::STATUS_LEASED, YouTubeTask::STATUS_PENDING])
                ->count();

            $target = $order->target_quantity;
            if ((int) $order->delivered + $inFlight >= $target) {
                return null;
            }

            $providerPayload = $order->provider_payload ?? [];
            $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];
            $perCall = max(1, (int) ($executionMeta['per_call'] ?? 1));

            $youtube = $providerPayload['youtube'] ?? [];
            $parsed = $youtube['parsed'] ?? [];
            $targetHashForLog = $parsed['target_hash'] ?? $linkHash;

            if ($this->actionLogService->hasPerformed(
                ProviderActionLogService::PROVIDER_YOUTUBE,
                $accountIdentity,
                $targetHashForLog,
                $action
            )) {
                return null;
            }

            $targetType = null;
            $normalizedTarget = null;
            $targetHashForRow = null;

            $global = null;
            $globalRowCreated = false;
            $previousGlobalState = null;

            if ($this->isStatefulAction($action)) {
                $norm = YouTubeTargetNormalizer::forSubscribeTarget($order);
                $targetType = $norm['target_type'];
                $normalizedTarget = $norm['normalized_target'];
                $targetHashForRow = $norm['target_hash'];

                $global = YouTubeAccountTargetState::query()
                    ->where('account_identity', $accountIdentity)
                    ->where('action', $action)
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
                            'action' => $action,
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
            if ($targetHashForRow !== null) {
                $payload['target_hash'] = $targetHashForRow;
                $payload['normalized_target'] = $normalizedTarget;
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

            return [
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
                    'description' => $service?->description ?? '',
                    'service_description' => $service?->description ?? $service?->name ?? '',
                ],
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name ?? '',
                ] : null,
            ];
        });
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
