<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ExternalPanel\ExternalPanelClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends an order to an external SMM provider panel via the v2 API.
 *
 * Flow: order is in STATUS_VALIDATING → this job sends it to the external panel →
 * stores the remote order ID → transitions to STATUS_IN_PROGRESS (or STATUS_PENDING).
 */
class SendOrderToExternalProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        // Atomically claim the order (same pattern as inspect jobs)
        $claimTtlMinutes = 10;

        $claimed = Order::query()
            ->whereKey($this->orderId)
            ->where('status', Order::STATUS_VALIDATING)
            ->where(function ($q) use ($claimTtlMinutes) {
                $q->whereNull('provider_sending_at')
                    ->orWhere('provider_sending_at', '<', now()->subMinutes($claimTtlMinutes));
            })
            ->update(['provider_sending_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $order = Order::with('service')->findOrFail($this->orderId);
        $service = $order->service;

        if (! $service || ! $service->isExternalProvider()) {
            Log::warning('SendOrderToExternalProvider: service is not external', [
                'order_id' => $this->orderId,
                'service_id' => $service?->id,
            ]);
            Order::query()->whereKey($this->orderId)->update(['provider_sending_at' => null]);

            return;
        }

        // Idempotency: if already sent (e.g. retry after crash), skip the HTTP call
        if (! empty($order->provider_order_id)) {
            Log::info('SendOrderToExternalProvider: already sent, skipping', [
                'order_id' => $this->orderId,
                'provider_order_id' => $order->provider_order_id,
            ]);
            $order->update([
                'status' => Order::STATUS_PENDING,
                'provider_sending_at' => null,
                'sent_to_provider_at' => $order->sent_to_provider_at ?? now(),
            ]);

            return;
        }

        try {
            $client = ExternalPanelClient::forProvider($service->provider);

            $result = $client->addOrder(
                $service->provider_service_id,
                $order->link,
                (int) $order->quantity,
            );

            $remoteOrderId = $result['order'];

            $order->update([
                'status' => Order::STATUS_PENDING,
                'provider' => $service->provider,
                'provider_order_id' => (string) $remoteOrderId,
                'provider_response' => $result,
                'provider_last_error' => null,
                'provider_last_error_at' => null,
                'provider_sending_at' => null,
                'sent_to_provider_at' => now(),
            ]);

            Log::info('Order sent to external provider', [
                'order_id' => $this->orderId,
                'provider' => $service->provider,
                'remote_order_id' => $remoteOrderId,
            ]);
        } catch (Throwable $e) {
            $order->update([
                'provider_last_error' => $e->getMessage(),
                'provider_last_error_at' => now(),
                'provider_sending_at' => null,
            ]);

            Log::error('Failed to send order to external provider', [
                'order_id' => $this->orderId,
                'provider' => $service->provider,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        // If order was already sent (provider_order_id set), don't mark as failed
        if (! empty($order->provider_order_id)) {
            $order->update([
                'status' => Order::STATUS_PENDING,
                'provider_sending_at' => null,
            ]);

            return;
        }

        // Mark as failed so it's visible in admin — requires manual refund or retry
        $order->update([
            'status' => Order::STATUS_FAIL,
            'provider_last_error' => 'All retries exhausted: '.$exception->getMessage(),
            'provider_last_error_at' => now(),
            'provider_sending_at' => null,
        ]);

        Log::error('SendOrderToExternalProviderJob permanently failed', [
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
