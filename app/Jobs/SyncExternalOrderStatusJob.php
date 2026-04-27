<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Service;
use App\Services\ExternalPanel\ExternalPanelClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Periodically polls external SMM panels for status updates on active orders.
 *
 * Groups orders by provider to minimize client instantiation, then polls each
 * order individually via the v2 status API.
 */
class SyncExternalOrderStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    /** Max orders to poll per run (prevent runaway). */
    private const BATCH_LIMIT = 200;

    /** Minimum seconds between polls for the same order. */
    private const MIN_POLL_INTERVAL = 60;

    public function handle(): void
    {
        $orders = Order::query()
            ->whereNotNull('provider_order_id')
            ->where('mode', Service::MODE_PROVIDER)
            ->whereIn('status', [
                Order::STATUS_PENDING,
                Order::STATUS_IN_PROGRESS,
                Order::STATUS_PROCESSING,
            ])
            ->where(function ($q) {
                $q->whereNull('provider_last_polled_at')
                    ->orWhere('provider_last_polled_at', '<', now()->subSeconds(self::MIN_POLL_INTERVAL));
            })
            ->orderBy('provider_last_polled_at')  // oldest-polled first
            ->limit(self::BATCH_LIMIT)
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        // Group by provider to reuse HTTP client per panel
        $grouped = $orders->groupBy('provider');

        foreach ($grouped as $providerCode => $providerOrders) {
            try {
                $client = ExternalPanelClient::forProvider($providerCode);
            } catch (\Throwable $e) {
                Log::error('SyncExternalOrderStatus: cannot create client', [
                    'provider' => $providerCode,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($providerOrders as $order) {
                $this->pollOrder($order, $client);
            }
        }
    }

    private function pollOrder(Order $order, ExternalPanelClient $client): void
    {
        try {
            $result = $client->orderStatus($order->provider_order_id);
        } catch (\Throwable $e) {
            $order->update([
                'provider_last_error' => $e->getMessage(),
                'provider_last_error_at' => now(),
                'provider_last_polled_at' => now(),
            ]);

            Log::warning('SyncExternalOrderStatus: poll failed', [
                'order_id' => $order->id,
                'provider_order_id' => $order->provider_order_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $qty = (int) $order->quantity;
        $updateData = [
            'provider_status_response' => $result,
            'provider_last_polled_at' => now(),
            'provider_last_error' => null,
            'provider_last_error_at' => null,
        ];

        // start_count
        if (isset($result['start_count']) && $result['start_count'] !== null) {
            $updateData['start_count'] = max(0, (int) $result['start_count']);
        }

        // remains → derive delivered
        if (isset($result['remains']) && $result['remains'] !== null) {
            $remains = max(0, min($qty, (int) $result['remains']));
            $updateData['remains'] = $remains;
            $updateData['delivered'] = max(0, $qty - $remains);
        }

        // Map provider status to local status (only allow forward transitions)
        if (! empty($result['status'])) {
            $mappedStatus = $this->mapStatus((string) $result['status']);

            // Don't overwrite locally-set partial/canceled
            if (! in_array($order->status, [Order::STATUS_PARTIAL, Order::STATUS_CANCELED], true)
                && $this->isForwardTransition($order->status, $mappedStatus)) {
                $updateData['status'] = $mappedStatus;

                if ($mappedStatus === Order::STATUS_COMPLETED) {
                    $updateData['completed_at'] = now();
                    $updateData['execution_phase'] = Order::EXECUTION_PHASE_COMPLETED;
                }
            }
        }

        $order->update($updateData);

        Log::debug('SyncExternalOrderStatus: polled', [
            'order_id' => $order->id,
            'remote_status' => $result['status'] ?? null,
            'delivered' => $updateData['delivered'] ?? null,
            'remains' => $updateData['remains'] ?? null,
        ]);
    }

    private function isForwardTransition(string $current, string $new): bool
    {
        $weight = [
            Order::STATUS_PENDING => 1,
            Order::STATUS_PROCESSING => 2,
            Order::STATUS_IN_PROGRESS => 3,
            Order::STATUS_COMPLETED => 4,
            Order::STATUS_PARTIAL => 4,
            Order::STATUS_FAIL => 4,
            Order::STATUS_CANCELED => 4,
        ];

        return ($weight[$new] ?? 0) >= ($weight[$current] ?? 0);
    }

    private function mapStatus(string $remoteStatus): string
    {
        $remoteStatus = strtolower(trim($remoteStatus));

        return match ($remoteStatus) {
            'pending' => Order::STATUS_PENDING,
            'processing' => Order::STATUS_PROCESSING,
            'in progress', 'in_progress', 'in-progress' => Order::STATUS_IN_PROGRESS,
            'completed', 'complete' => Order::STATUS_COMPLETED,
            'partial' => Order::STATUS_PARTIAL,
            'canceled', 'cancelled' => Order::STATUS_CANCELED,
            'failed', 'fail' => Order::STATUS_FAIL,
            default => Order::STATUS_PROCESSING,
        };
    }
}
