<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Provider\ProviderClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class UpdateProviderOrderStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    public function __construct(public int $orderId) {}

    public function handle(ProviderClient $client): void
    {
        Redis::throttle('provider:status')
            ->allow((int) config('services.provider.status_rate_limit_per_second', 5))
            ->every(1)
            ->then(function () use ($client) {
                $this->process($client);
            }, function () {
                $this->release(1);
            });
    }

    private function process(ProviderClient $client): void
    {
        $order = Order::query()->find($this->orderId);

        if (!$order) {
            Log::warning('Order not found for provider status update', ['order_id' => $this->orderId]);
            return;
        }

        if (empty($order->provider_order_id)) {
            Log::warning('Order missing provider_order_id, cannot fetch status', [
                'order_id' => $this->orderId,
            ]);
            return;
        }

        // 1) Check if webhook was received recently (webhook takes precedence)
        $staleMinutes = (int) config('services.provider.webhook_stale_minutes', 15);
        if ($order->provider_webhook_received_at) {
            $webhookAge = now()->diffInMinutes($order->provider_webhook_received_at);
            if ($webhookAge < $staleMinutes) {
                Log::info('Skipping polling: recent webhook exists', [
                    'order_id' => $this->orderId,
                    'webhook_age_minutes' => $webhookAge,
                ]);
                return;
            }
        }

        // 2) Atomically acquire lock to prevent concurrent polling
        $lockTtlMinutes = (int) config('services.provider.sync_lock_ttl_minutes', 5);
        $lockExpiry = now()->subMinutes($lockTtlMinutes);
        $lockOwner = $this->job->uuid() ?? $this->job->getJobId() ?? uniqid('poll-', true);

        $lockAcquired = Order::query()
            ->whereKey($this->orderId)
            ->where(function ($query) use ($lockExpiry) {
                $query->whereNull('provider_status_sync_lock_at')
                    ->orWhere('provider_status_sync_lock_at', '<', $lockExpiry);
            })
            ->update([
                'provider_status_sync_lock_at' => now(),
                'provider_status_sync_lock_owner' => $lockOwner,
            ]);

        if ($lockAcquired === 0) {
            // Lock already held by another job
            Log::debug('Skipping polling: lock already held', [
                'order_id' => $this->orderId,
                'lock_owner' => $order->provider_status_sync_lock_owner,
            ]);
            return;
        }

        // Reload order to get fresh data
        $order->refresh();

        // Double-check webhook after acquiring lock (race condition protection)
        if ($order->provider_webhook_received_at) {
            $webhookAge = now()->diffInMinutes($order->provider_webhook_received_at);
            if ($webhookAge < $staleMinutes) {
                // Release lock and exit
                $order->update([
                    'provider_status_sync_lock_at' => null,
                    'provider_status_sync_lock_owner' => null,
                ]);
                Log::info('Skipping polling: webhook received after lock acquisition', [
                    'order_id' => $this->orderId,
                ]);
                return;
            }
        }

        // 3) Call provider API (catch exceptions)
        try {
            $result = $client->fetchStatus((string) $order->provider_order_id);
        } catch (\Throwable $e) {
            // Release lock on exception
            $order->update([
                'provider_last_error' => $e->getMessage(),
                'provider_last_error_at' => now(),
                'provider_status_sync_lock_at' => null,
                'provider_status_sync_lock_owner' => null,
            ]);
            throw $e;
        }

        // Always save raw response
        $order->update([
            'provider_status_response' => $result['raw'] ?? null,
        ]);

        if (empty($result['ok'])) {
            $errorMessage = (string) ($result['error'] ?? 'Unknown error from provider');

            // Release lock on failure
            $order->update([
                'provider_last_error' => $errorMessage,
                'provider_last_error_at' => now(),
                'provider_status_sync_lock_at' => null,
                'provider_status_sync_lock_owner' => null,
            ]);

            Log::warning('Failed to fetch order status from provider', [
                'order_id' => $this->orderId,
                'provider_order_id' => $order->provider_order_id,
                'error' => $errorMessage,
                'attempt' => $this->attempts(),
            ]);

            throw new \RuntimeException("Failed to fetch order status from provider: {$errorMessage}");
        }

        // Success: build update data safely
        $updateData = [
            'provider_last_error' => null,
            'provider_last_error_at' => null,
        ];

        $qty = (int) $order->quantity;

        // start_count
        if (array_key_exists('start_count', $result) && $result['start_count'] !== null) {
            $updateData['start_count'] = max(0, (int) $result['start_count']);
        }

        $deliveredFromProvider = array_key_exists('delivered', $result) ? $result['delivered'] : null;
        $remainsFromProvider = array_key_exists('remains', $result) ? $result['remains'] : null;

        // delivered/remains consistency
        if ($deliveredFromProvider !== null) {
            $delivered = max(0, min($qty, (int) $deliveredFromProvider));
            $updateData['delivered'] = $delivered;

            if ($remainsFromProvider === null) {
                $updateData['remains'] = max(0, $qty - $delivered);
            }
        }

        if ($remainsFromProvider !== null) {
            $remains = max(0, min($qty, (int) $remainsFromProvider));
            $updateData['remains'] = $remains;

            if ($deliveredFromProvider === null) {
                $updateData['delivered'] = max(0, $qty - $remains);
            }
        }

        // status
        if (!empty($result['status'])) {
            $updateData['status'] = $this->mapProviderStatus((string) $result['status']);
        }

        // 4) Update order with status data and release lock
        $updateData['provider_last_polled_at'] = now();
        $updateData['provider_status_sync_lock_at'] = null;
        $updateData['provider_status_sync_lock_owner'] = null;

        $order->update($updateData);

        Log::info('Order status updated from provider (polling)', [
            'order_id' => $this->orderId,
            'provider_order_id' => $order->provider_order_id,
            'status' => $result['status'] ?? null,
            'delivered' => $updateData['delivered'] ?? null,
            'remains' => $updateData['remains'] ?? null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order) {
            // Release lock on job failure
            $order->update([
                'provider_last_error' => $exception->getMessage(),
                'provider_last_error_at' => now(),
                'provider_status_sync_lock_at' => null,
                'provider_status_sync_lock_owner' => null,
            ]);
        }

        Log::error('UpdateProviderOrderStatus job failed', [
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
        ]);
    }

    protected function mapProviderStatus(string $providerStatus): string
    {
        $providerStatus = strtolower(trim($providerStatus));

        $statusMap = [
            'pending' => Order::STATUS_PENDING,
            'processing' => Order::STATUS_PROCESSING,
            'in_progress' => Order::STATUS_IN_PROGRESS,
            'in-progress' => Order::STATUS_IN_PROGRESS,
            'completed' => Order::STATUS_COMPLETED,
            'complete' => Order::STATUS_COMPLETED,
            'partial' => Order::STATUS_PARTIAL,
            'failed' => Order::STATUS_FAIL,
            'fail' => Order::STATUS_FAIL,
            'canceled' => Order::STATUS_CANCELED,
            'cancelled' => Order::STATUS_CANCELED,
        ];

        return $statusMap[$providerStatus] ?? Order::STATUS_PROCESSING;
    }
}
