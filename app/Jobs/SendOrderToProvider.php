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

class SendOrderToProvider implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(public int $orderId) {}

    public function handle(ProviderClient $client): void
    {
        Redis::throttle('provider:send')
            ->allow((int) config('services.provider.rate_limit_per_second', 5))
            ->every(1)
            ->then(function () use ($client) {
                $this->process($client);
            }, function () {
                // Could not get a slot this second -> retry shortly
                $this->release(1);
            });
    }

    private function process(ProviderClient $client): void
    {
        // 1) Atomically "claim" the order so two workers can't send it twice.
        $claimed = Order::query()
            ->whereKey($this->orderId)
            ->whereNull('provider_order_id')
            ->whereNull('sent_to_provider_at')
            ->whereNull('provider_sending_at')
            ->update([
                'provider_sending_at' => now(),
            ]);

        if ($claimed === 0) {
            // Already sent OR being sent by another worker OR doesn't exist
            return;
        }

        $order = Order::query()->find($this->orderId);

        if (!$order) {
            Log::warning('Order not found after claim (unexpected)', ['order_id' => $this->orderId]);
            return;
        }

        // Extra idempotency guard
        if ($order->provider_order_id || $order->sent_to_provider_at) {
            return;
        }

        // 2) Save payload (for debugging/audit)
        $payload = [
            'service_id' => $order->service_id,
            'link' => $order->link,
            'quantity' => $order->quantity,
            'local_order_id' => $order->id,
        ];

        $order->provider_payload = $payload;
        $order->save();

        // 3) Call provider API (catch exceptions)
        try {
            $result = $client->createOrder($order); // expects ['ok','provider_order_id','status','start_count','raw','error']
        } catch (\Throwable $e) {
            $order->update([
                'provider_last_error' => $e->getMessage(),
                'provider_last_error_at' => now(),
                'provider_sending_at' => null, // release claim so retries can run
            ]);
            throw $e;
        }

        // 4) Always save raw response (even on failure)
        $order->provider_response = $result['raw'] ?? null;
        $order->save();

        // 5) Success
        if (!empty($result['ok']) && !empty($result['provider_order_id'])) {
            $updateData = [
                'provider_order_id' => (string) $result['provider_order_id'],
                'sent_to_provider_at' => now(),
                'provider_last_error' => null,
                'provider_last_error_at' => null,
                'provider_sending_at' => null,
            ];

            // status
            if (!empty($result['status'])) {
                $updateData['status'] = $this->mapProviderStatus((string) $result['status']);
            } elseif ($order->status === Order::STATUS_AWAITING) {
                $updateData['status'] = Order::STATUS_PENDING;
            }

            // start_count
            if (array_key_exists('start_count', $result) && $result['start_count'] !== null) {
                $updateData['start_count'] = (int) $result['start_count'];
            }

            $order->update($updateData);

            Log::info('Order sent to provider successfully', [
                'order_id' => $this->orderId,
                'provider_order_id' => $result['provider_order_id'],
            ]);

            return;
        }

        // 6) Failure: store error + release claim + throw for retry
        $errorMessage = (string) ($result['error'] ?? 'Unknown error from provider');

        $order->update([
            'provider_last_error' => $errorMessage,
            'provider_last_error_at' => now(),
            'provider_sending_at' => null, // release claim so retries can run
        ]);

        Log::warning('Failed to send order to provider', [
            'order_id' => $this->orderId,
            'error' => $errorMessage,
        ]);

        throw new \RuntimeException("Failed to send order to provider: {$errorMessage}");
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order) {
            $order->update([
                'provider_last_error' => $exception->getMessage(),
                'provider_last_error_at' => now(),
                'provider_sending_at' => null,
            ]);
        }

        Log::error('SendOrderToProvider job failed', [
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
            'partial' => Order::STATUS_PARTIAL,
            'failed' => Order::STATUS_FAIL,
            'fail' => Order::STATUS_FAIL,
            'canceled' => Order::STATUS_CANCELED,
            'cancelled' => Order::STATUS_CANCELED,
        ];

        return $statusMap[$providerStatus] ?? Order::STATUS_PENDING;
    }
}
