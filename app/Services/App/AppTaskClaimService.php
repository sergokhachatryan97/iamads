<?php

namespace App\Services\App;

use App\Models\AppTask;
use App\Models\Order;
use App\Services\ProviderActionLogService;
use App\Support\App\AppTargetNormalizer;
use App\Support\Performer\OrderDripfeedClaimHelper;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * App performer claim: tasks for app download + review (positive or custom with star).
 * Mirrors YouTubeTaskClaimService: one task per account_identity, dripfeed-aware,
 * execution_meta.next_run_at support, per-account comment_text, star in description.
 */
class AppTaskClaimService
{
    private const LEASE_TTL_SECONDS = 180;
    private const LAST_CLAIMED_ORDER_CACHE_KEY = 'app:claim:last_order_id';

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
            ->when($lastClaimedOrderId > 0, fn ($q) => $q->where('id', '<=', $lastClaimedOrderId))
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
            ->whereHas('service', fn ($q) => $q->whereHas('category', fn ($q2) => $q2->where('link_driver', 'app')));
    }

    /**
     * When is this order due for claiming (dripfeed or execution_meta.next_run_at).
     * Aligns with YouTubeTaskClaimService::computeOrderDueAt.
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

    private function resolveExecutionPlan(Order $order): array
    {
        return AppExecutionPlanResolver::resolve($order);
    }

    private function tryClaimForOrder(Order $order, string $accountIdentity): ?array
    {
        return DB::transaction(function () use ($order, $accountIdentity): ?array {
            $order = Order::query()
                ->where('id', $order->id)
                ->where('remains', '>', 0)
                ->whereHas('service', fn ($q) => $q->whereHas('category', fn ($q2) => $q2->where('link_driver', 'app')))
                ->lockForUpdate()
                ->with(['service', 'service.category'])
                ->first();

            if ($order === null) {
                return null;
            }

            $plan = $this->resolveExecutionPlan($order);
            $action = $plan['action'];
            $steps = $plan['steps'];

            $link = trim((string) ($order->link ?? ''));
            if ($link === '') {
                return null;
            }

            $linkHash = AppTargetNormalizer::linkHash($link);
            $targetHash = AppTargetNormalizer::targetHash($order);

            if (!OrderDripfeedClaimHelper::canClaimTaskNow($order)) {
                return null;
            }
            $order->refresh();

            $inFlight = AppTask::query()
                ->where('order_id', $order->id)
                ->whereIn('status', [AppTask::STATUS_LEASED])
                ->count();

            $target = $order->target_quantity;
            if ((int) $order->delivered + $inFlight >= $target) {
                return null;
            }

            $actionNames = AppExecutionPlanResolver::stepsToActionNames($steps);
            if ($this->hasStepConflict($accountIdentity, $targetHash, $actionNames)) {
                return null;
            }

            $actionForLog = count($steps) > 1
                ? AppExecutionPlanResolver::compositeActionForLog($steps)
                : $action;

            if (AppTask::query()
                ->where('account_identity', $accountIdentity)
                ->where('order_id', $order->id)
                ->where('link_hash', $linkHash)
                ->where('action', $action)
                ->exists()) {
                return null;
            }

            $leasedUntil = now()->addSeconds(self::LEASE_TTL_SECONDS);
            $payload = [
                'order_id' => $order->id,
                'per_call' => 1,
                'action' => $action,
                'steps' => $steps,
                'target_hash' => $targetHash,
            ];

            // comment_text: pick one per account_identity (like YouTube) — index = delivered + inFlight
            $commentTextForTask = null;
            if (in_array('custom_review', $steps, true) && !empty(trim((string) ($order->comment_text ?? '')))) {
                $comments = array_values(array_filter(array_map('trim', explode("\n", (string) $order->comment_text))));
                $index = (int) $order->delivered + $inFlight;
                if (!empty($comments)) {
                    $commentTextForTask = $comments[$index % count($comments)];
                } else {
                    $commentTextForTask = trim((string) $order->comment_text);
                }
                if ($commentTextForTask !== '') {
                    $payload['comment_text'] = $commentTextForTask;
                }
            }

            // star_rating: add for custom_review or positive_review (column first, fallback to provider_payload)
            $starRating = $order->star_rating ?? (($order->provider_payload ?? [])['star_rating'] ?? null);
            if ($starRating !== null && $starRating >= 1 && $starRating <= 5) {
                if (in_array('custom_review', $steps, true) || in_array('positive_review', $steps, true)) {
                    $payload['star_rating'] = (int) $starRating;
                }
            }

            try {
                $task = AppTask::create([
                    'order_id' => $order->id,
                    'account_identity' => $accountIdentity,
                    'action' => $action,
                    'link' => $link,
                    'link_hash' => $linkHash,
                    'target_hash' => $targetHash,
                    'status' => AppTask::STATUS_LEASED,
                    'leased_until' => $leasedUntil,
                    'payload' => $payload,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                Log::debug('App task claim duplicate', [
                    'account_identity' => $accountIdentity,
                    'order_id' => $order->id,
                    'action' => $action,
                ]);
                return null;
            }

            OrderDripfeedClaimHelper::afterTaskClaimed($order);
            $order->update(['status' => Order::STATUS_IN_PROGRESS]);

            Log::debug('App task claimed', [
                'task_id' => $task->id,
                'order_id' => $order->id,
                'action' => $action,
            ]);

            $service = $order->service;
            $category = $service?->category;

            $serviceDescription = $service?->description_for_performer ?? '';
            if ($commentTextForTask !== null && trim((string) $commentTextForTask) !== '') {
                $serviceDescription .= ($serviceDescription !== '' ? "\n" : '') .sprintf('Review: %d', $commentTextForTask);
            }
            if (isset($payload['star_rating'])) {
                $serviceDescription .= ($serviceDescription !== '' ? "\n" : '') . sprintf('Star rating: %d', $payload['star_rating']);
            }

            $result = [
                'task_id' => $task->id,
                'link' => $link,
                'link_hash' => $linkHash,
                'action' => $action,
                'order_id' => (int) $order->id,
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
                    'description' => $serviceDescription,
                    'service_description' => $serviceDescription,
                ],
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name ?? '',
                ] : null,
            ];

            if (count($steps) > 1) {
                $result['mode'] = 'combo';
                $result['steps'] = $steps;
            }
            if (!empty($payload['comment_text'] ?? '')) {
                $result['comment_text'] = $payload['comment_text'];
            }
            if (isset($payload['star_rating'])) {
                $result['star_rating'] = $payload['star_rating'];
            }

            return $result;
        });
    }

    private function hasStepConflict(string $accountIdentity, string $targetHash, array $actionNames): bool
    {
        $activeStatuses = [AppTask::STATUS_LEASED, AppTask::STATUS_PENDING];

        $activeTasks = AppTask::query()
            ->where('account_identity', $accountIdentity)
            ->where('target_hash', $targetHash)
            ->whereIn('status', $activeStatuses)
            ->get(['id', 'action', 'payload']);

        foreach ($activeTasks as $task) {
            $payload = $task->payload ?? [];
            $steps = is_array($payload) && isset($payload['steps']) ? $payload['steps'] : [$task->action];
            $existingNames = AppExecutionPlanResolver::stepsToActionNames($steps);
            if (array_intersect($actionNames, $existingNames) !== []) {
                return true;
            }
        }

        foreach ($actionNames as $actionName) {
            if ($this->actionLogService->hasPerformed(
                ProviderActionLogService::PROVIDER_APP,
                $accountIdentity,
                $targetHash,
                $actionName
            )) {
                return true;
            }
        }

        if (count($actionNames) > 1) {
            $compositeAction = AppExecutionPlanResolver::compositeActionForLog($actionNames);
            if ($this->actionLogService->hasPerformed(
                ProviderActionLogService::PROVIDER_APP,
                $accountIdentity,
                $targetHash,
                $compositeAction
            )) {
                return true;
            }
        }

        return false;
    }
}
