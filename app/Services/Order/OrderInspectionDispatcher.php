<?php

namespace App\Services\Order;

use App\Jobs\InspectAppLinkJob;
use App\Jobs\InspectTelegramLinkJob;
use App\Jobs\InspectYouTubeLinkJob;
use App\Models\Order;

/**
 * Dispatches the appropriate link-inspection job for an order based on the
 * category's link_driver.
 */
class OrderInspectionDispatcher
{
    /**
     * Dispatch inspection for an order. Telegram → InspectTelegramLinkJob;
     * YouTube → InspectYouTubeLinkJob; app → InspectAppLinkJob.
     * Other drivers are no-op until implemented.
     */
    public function dispatch(Order $order): void
    {
        $order->loadMissing(['service.category']);

        $driver = $order->service?->category?->link_driver ?? 'generic';

        if ($driver === 'telegram') {
            InspectTelegramLinkJob::dispatch($order->id)
                ->onQueue('tg-inspect')
                ->afterCommit();

            return;
        }

        if ($driver === 'youtube') {
            InspectYouTubeLinkJob::dispatch($order->id)
                ->onQueue('yt-inspect')
                ->afterCommit();

            return;
        }

        if ($driver === 'app') {
            InspectAppLinkJob::dispatch($order->id)
                ->onQueue('app-inspect')
                ->afterCommit();

            return;
        }

        // Other drivers: no inspection job (order may stay VALIDATING or be advanced by other logic).
    }
}
