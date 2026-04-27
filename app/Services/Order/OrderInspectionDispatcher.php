<?php

namespace App\Services\Order;

use App\Jobs\InspectAppLinkJob;
use App\Jobs\InspectMaxLinkJob;
use App\Jobs\InspectTelegramLinkJob;
use App\Jobs\InspectYouTubeLinkJob;
use App\Jobs\SendOrderToExternalProviderJob;
use App\Models\Order;

/**
 * Dispatches the appropriate link-inspection job for an order based on the
 * category's link_driver. External-provider services skip inspection and
 * are sent directly to the remote SMM panel.
 */
class OrderInspectionDispatcher
{
    /**
     * Dispatch inspection for an order. External provider services are routed
     * to SendOrderToExternalProviderJob; internal services go through the
     * driver-specific link inspection pipeline.
     */
    public function dispatch(Order $order): void
    {
        $order->loadMissing(['service.category']);

        // External provider services skip link inspection — the remote panel validates links.
        if ($order->service?->isExternalProvider()) {
            SendOrderToExternalProviderJob::dispatch($order->id)
                ->onQueue('external-provider')
                ->afterCommit();

            return;
        }

        $driver = $order->service?->category?->link_driver ?? 'generic';

        if ($driver === 'telegram') {
            InspectTelegramLinkJob::dispatch($order->id)
                ->onQueue('tg-panel-inspect')
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

        if ($driver === 'max') {
            InspectMaxLinkJob::dispatch($order->id)
                ->onQueue('max-inspect')
                ->afterCommit();

            return;
        }

        // Other drivers: no inspection job (order may stay VALIDATING or be advanced by other logic).
    }
}
