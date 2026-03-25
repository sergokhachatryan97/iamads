<?php

namespace App\Services\Telegram;

use App\Models\Order;
use App\Models\TelegramOrderMembership;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves completed/canceled Telegram orders into execution_phase = unsubscribing when
 * duration-based unsubscribe is due, so performer getOrder can claim unsubscribe tasks.
 */
class TelegramUnsubscribePhaseActivator
{
    /**
     * Activate unsubscribing phase for all eligible orders. Idempotent per order.
     *
     * @return int Number of orders updated this run
     */
    public function activate(): int
    {
        $activated = 0;
        $activatedIds = [];

        Order::query()
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELED])
            ->whereNotNull('completed_at')
            ->where(function ($q) {
                $q->whereNull('execution_phase')
                    ->orWhere('execution_phase', '!=', Order::EXECUTION_PHASE_UNSUBSCRIBING);
            })
            ->whereHas('service', function ($q) {
                $q->whereHas('category', function ($q2) {
                    $q2->where('link_driver', 'telegram');
                })
                    ->whereNotNull('duration_days')
                    ->where('duration_days', '>', 0);
            })
            ->whereHas('telegramOrderMemberships', function ($q) {
                $q->where('state', TelegramOrderMembership::STATE_SUBSCRIBED)
                    ->whereNull('unsubscribed_at');
            })
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (&$activated, &$activatedIds): void {
                foreach ($orders as $order) {
                    $order->loadMissing('service');

                    if (!$this->isDueForUnsubscribePhase($order)) {
                        continue;
                    }

                    DB::transaction(function () use ($order, &$activated, &$activatedIds): void {
                        $locked = Order::query()
                            ->whereKey($order->id)
                            ->lockForUpdate()
                            ->first();

                        if ($locked === null) {
                            return;
                        }

                        if ($locked->execution_phase === Order::EXECUTION_PHASE_UNSUBSCRIBING) {
                            return;
                        }

                        if (!in_array($locked->status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELED], true)) {
                            return;
                        }

                        if ($locked->completed_at === null) {
                            return;
                        }

                        $locked->loadMissing('service');
                        if (!$this->serviceEligibleForPhase($locked)) {
                            return;
                        }

                        if (!$this->isDueForUnsubscribePhase($locked)) {
                            return;
                        }

                        if (!$this->hasSubscribedMembershipAwaitingUnsubscribe($locked->id)) {
                            return;
                        }

                        $locked->update(['execution_phase' => Order::EXECUTION_PHASE_UNSUBSCRIBING]);
                        $activated++;
                        $activatedIds[] = (int) $locked->id;
                    });
                }
            });

        if ($activated > 0) {
            Log::info('Telegram orders moved to unsubscribing phase', [
                'count' => $activated,
                'order_ids' => array_slice($activatedIds, 0, 50),
            ]);
        }

        return $activated;
    }

    /**
     * Same due window as TelegramTaskClaimService::claimUnsubscribe()
     * (completed_at + max(1, duration_days)).
     */
    private function isDueForUnsubscribePhase(Order $order): bool
    {
        return $this->unsubscribeDueAt($order)?->lte(now()) ?? false;
    }

    private function unsubscribeDueAt(Order $order): ?Carbon
    {
        if ($order->completed_at === null) {
            return null;
        }

        $service = $order->service;
        if ($service === null) {
            return null;
        }

        $durationDays = (int) ($service->duration_days ?? 0);
        if ($durationDays <= 0) {
            return null;
        }

        return $order->completed_at->copy()
            ->addDays(max(1, $durationDays));
    }

    private function serviceEligibleForPhase(Order $order): bool
    {
        $service = $order->service;
        if ($service === null) {
            return false;
        }

        $category = $service->relationLoaded('category') ? $service->category : $service->category()->first();
        if ($category?->link_driver !== 'telegram') {
            return false;
        }

        // Match claimUnsubscribe: duration_days must be positive for timed unsubscribe
        if ($service->duration_days === null || (int) $service->duration_days <= 0) {
            return false;
        }

        return true;
    }

    private function hasSubscribedMembershipAwaitingUnsubscribe(int $orderId): bool
    {
        return TelegramOrderMembership::query()
            ->where('order_id', $orderId)
            ->where('state', TelegramOrderMembership::STATE_SUBSCRIBED)
            ->whereNull('unsubscribed_at')
            ->exists();
    }
}
