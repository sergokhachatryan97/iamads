<?php

namespace App\Services\Telegram;

use App\Models\Order;
use App\Models\TelegramOrderMembership;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves completed/canceled Telegram orders into execution_phase = unsubscribing when
 * duration-based unsubscribe is due, so performer getOrder can claim unsubscribe tasks.
 */
class TelegramUnsubscribePhaseActivator
{
    public function activate(): int
    {
        $categoryId = $this->getTelegramCategoryId();
        if (! $categoryId) {
            return 0;
        }

        // Get service IDs with duration_days > 0 in telegram category (cached 1hr)
        $serviceIds = $this->getEligibleServiceIds($categoryId);
        if (empty($serviceIds)) {
            return 0;
        }

        $now = now();

        // Single query: find all candidate order IDs.
        // No nested whereHas — uses direct column filters + exists subquery on memberships.
        $candidateIds = DB::table('orders')
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELED])
            ->whereNotNull('completed_at')
            ->where('category_id', $categoryId)
            ->whereIn('service_id', $serviceIds)
            ->where(function ($q) {
                $q->whereNull('execution_phase')
                    ->orWhere('execution_phase', '!=', Order::EXECUTION_PHASE_UNSUBSCRIBING);
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('telegram_order_memberships')
                    ->whereColumn('telegram_order_memberships.order_id', 'orders.id')
                    ->where('state', TelegramOrderMembership::STATE_SUBSCRIBED)
                    ->whereNull('unsubscribed_at');
            })
            ->pluck('id')
            ->all();

        if (empty($candidateIds)) {
            return 0;
        }

        // Load orders with service in one query (no N+1)
        $orders = Order::query()
            ->whereIn('id', $candidateIds)
            ->with('service')
            ->get();

        // Filter by due date in PHP — avoids complex SQL date math
        $dueOrderIds = [];
        foreach ($orders as $order) {
            $durationDays = (int) ($order->service?->duration_days ?? 0);
            if ($durationDays <= 0 || ! $order->completed_at) {
                continue;
            }

            $dueAt = $order->completed_at->copy()->addDays(max(1, $durationDays));
            if ($dueAt->lte($now)) {
                $dueOrderIds[] = $order->id;
            }
        }

        if (empty($dueOrderIds)) {
            return 0;
        }

        // Bulk update — single query instead of per-order transaction
        $activated = Order::query()
            ->whereIn('id', $dueOrderIds)
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELED])
            ->where(function ($q) {
                $q->whereNull('execution_phase')
                    ->orWhere('execution_phase', '!=', Order::EXECUTION_PHASE_UNSUBSCRIBING);
            })
            ->update(['execution_phase' => Order::EXECUTION_PHASE_UNSUBSCRIBING]);

        if ($activated > 0) {
            Log::info('Telegram orders moved to unsubscribing phase', [
                'count' => $activated,
                'order_ids' => array_slice($dueOrderIds, 0, 50),
            ]);
        }

        return $activated;
    }

    private function getTelegramCategoryId(): ?int
    {
        return Cache::remember('tg:category_id', 3600, fn () => \App\Models\Category::where('link_driver', 'telegram')->value('id')
        );
    }

    private function getEligibleServiceIds(int $categoryId): array
    {
        return Cache::remember('tg:unsub_service_ids', 3600, fn () => DB::table('services')
            ->where('category_id', $categoryId)
            ->whereNotNull('duration_days')
            ->where('duration_days', '>', 0)
            ->pluck('id')
            ->all()
        );
    }
}
