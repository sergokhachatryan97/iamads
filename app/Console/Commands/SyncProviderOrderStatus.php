<?php

namespace App\Console\Commands;

use App\Jobs\UpdateProviderOrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

class SyncProviderOrderStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:sync-provider-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync order statuses from provider API for active orders (polling fallback)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $staleMinutes = (int) config('services.provider.webhook_stale_minutes', 15);
        $pollMinMinutes = (int) config('services.provider.poll_min_minutes', 5);

        $staleThreshold = now()->subMinutes($staleMinutes);
        $pollMinThreshold = now()->subMinutes($pollMinMinutes);

        // Select only active orders that need polling
        $query = Order::query()
            ->whereNotNull('provider_order_id')
            ->whereIn('status', [
                Order::STATUS_PENDING,
                Order::STATUS_PROCESSING,
                Order::STATUS_IN_PROGRESS,
            ])
            // Skip orders with recent webhooks (webhook takes precedence)
            ->where(function ($q) use ($staleThreshold) {
                $q->whereNull('provider_webhook_received_at')
                    ->orWhere('provider_webhook_received_at', '<', $staleThreshold);
            })
            // Skip orders polled recently
            ->where(function ($q) use ($pollMinThreshold) {
                $q->whereNull('provider_last_polled_at')
                    ->orWhere('provider_last_polled_at', '<', $pollMinThreshold);
            });

        $total = $query->count();

        if ($total === 0) {
            $this->info('No orders to sync.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} orders to sync.");

        $dispatched = 0;

        // Process in chunks to avoid memory issues
        $query->chunkById(500, function ($orders) use (&$dispatched) {
            foreach ($orders as $order) {
                // Dispatch to dedicated queue for provider status updates
                UpdateProviderOrderStatus::dispatch($order->id)
                    ->onQueue('provider_status');
                $dispatched++;
            }
        });

        $this->info("Dispatched {$dispatched} status update jobs to 'provider_status' queue.");

        return self::SUCCESS;
    }
}
