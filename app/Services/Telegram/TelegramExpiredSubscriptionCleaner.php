<?php

namespace App\Services\Telegram;

use App\Models\Order;
use App\Models\TelegramAccountLinkState;
use App\Models\TelegramOrderMembership;
use App\Models\TelegramTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marks TelegramOrderMembership and TelegramAccountLinkState as unsubscribed
 * for completed/canceled orders whose service duration_days has elapsed,
 * so the accounts become available to subscribe to the same link again.
 */
class TelegramExpiredSubscriptionCleaner
{
    public function clean(): int
    {
        $categoryId = $this->getTelegramCategoryId();
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

        // Get memberships to clean, so we can also update their link states
        $memberships = TelegramOrderMembership::query()
            ->whereIn('order_id', $dueOrderIds)
            ->where('state', TelegramOrderMembership::STATE_SUBSCRIBED)
            ->whereNull('unsubscribed_at')
            ->get();

        if ($memberships->isEmpty()) {
            return 0;
        }

        $cleaned = 0;

        foreach ($memberships as $membership) {
            $membership->update([
                'state' => TelegramOrderMembership::STATE_UNSUBSCRIBED,
                'unsubscribed_at' => $now,
            ]);
            $cleaned++;

            // Mark the subscribe task as unsubscribed for statistics
            if ($membership->subscribed_task_id) {
                TelegramTask::query()
                    ->where('id', $membership->subscribed_task_id)
                    ->where('status', TelegramTask::STATUS_DONE)
                    ->update(['status' => TelegramTask::STATUS_UNSUBSCRIBED]);
            }

            // Release all blocking link_states for this phone+link (duration expired)
            TelegramAccountLinkState::query()
                ->where('account_phone', $membership->account_phone)
                ->where('link_hash', $membership->link_hash)
                ->whereIn('state', TelegramAccountLinkState::BLOCKING_STATES)
                ->update(['state' => TelegramAccountLinkState::STATE_EXPIRED]);
        }

        if ($cleaned > 0) {
            Log::info('TelegramExpiredSubscriptionCleaner: cleaned expired subscriptions', [
                'count' => $cleaned,
                'order_ids' => array_slice($dueOrderIds, 0, 50),
            ]);
        }

        return $cleaned;
    }

    private function getTelegramCategoryId(): ?int
    {
        return Cache::remember('tg:category_id', 3600, fn () => \App\Models\Category::where('link_driver', 'telegram')->value('id'));
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
