<?php

namespace App\Services\Max;

use App\Models\MaxTask;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deletes MaxTask subscribe records for completed/canceled orders
 * whose service duration_days has elapsed, so the accounts become
 * available to subscribe to the same link again.
 */
class MaxExpiredSubscriptionCleaner
{
    public function clean(): int
    {
        $categoryId = $this->getMaxCategoryId();
        if (! $categoryId) {
            return 0;
        }

        $serviceIds = $this->getEligibleServiceIds($categoryId);
        if (empty($serviceIds)) {
            return 0;
        }

        $now = now();

        $candidateIds = DB::table('orders')
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELED])
            ->whereNotNull('completed_at')
            ->where('category_id', $categoryId)
            ->whereIn('service_id', $serviceIds)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('max_tasks')
                    ->whereColumn('max_tasks.order_id', 'orders.id')
                    ->where('status', MaxTask::STATUS_DONE)
                    ->whereIn('action', ['subscribe', 'bot_start', 'view', 'react', 'repost']);
            })
            ->pluck('id')
            ->all();

        if (empty($candidateIds)) {
            return 0;
        }

        $orders = Order::query()
            ->whereIn('id', $candidateIds)
            ->with('service')
            ->get();

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

        $updated = MaxTask::query()
            ->whereIn('order_id', $dueOrderIds)
            ->where('status', MaxTask::STATUS_DONE)
            ->whereIn('action', ['subscribe', 'bot_start', 'view', 'react', 'repost'])
            ->update(['status' => MaxTask::STATUS_UNSUBSCRIBED]);

        if ($updated > 0) {
            Log::info('MaxExpiredSubscriptionCleaner: marked expired subscribe tasks as unsubscribed', [
                'count' => $updated,
                'order_ids' => array_slice($dueOrderIds, 0, 50),
            ]);
        }

        return $updated;
    }

    private function getMaxCategoryId(): ?int
    {
        return Cache::remember('max:category_id', 3600, fn () => \App\Models\Category::where('link_driver', 'max')->value('id'));
    }

    private function getEligibleServiceIds(int $categoryId): array
    {
        return Cache::remember('max:unsub_service_ids', 3600, fn () => DB::table('services')
            ->where('category_id', $categoryId)
            ->whereNotNull('duration_days')
            ->where('duration_days', '>', 0)
            ->pluck('id')
            ->all()
        );
    }
}
