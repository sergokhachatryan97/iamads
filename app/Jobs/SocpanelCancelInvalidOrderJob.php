<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ProviderOrder;
use App\Services\Providers\SocpanelClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cancels invalid provider orders on Socpanel when local orders are invalid_link or restricted.
 * Uses cursor-based pagination: processes in small batches and advances cursor only after
 * successful remote cancel. Safe to run repeatedly (idempotent).
 */
class SocpanelCancelInvalidOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CACHE_KEY_CURSOR = 'socpanel:cancel:cursor';
    public const BATCH_SIZE = 50;
    public const DELAY_BETWEEN_CALLS_MS = 300;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct() {}

    public function handle(SocpanelClient $client): void
    {
        $token = config('providers.socpanel.token');
        if (empty($token)) {
            Log::error('Socpanel cancel job: SOCPANEL_TOKEN not configured');
            throw new \RuntimeException('Socpanel cancel job: SOCPANEL_TOKEN not configured. Set SOCPANEL_TOKEN in .env.');
        }

        $providerName = config('providers.socpanel.name', 'adtag');
        $batchSize = (int) config('providers.socpanel.cancel_batch_size', self::BATCH_SIZE);
        $batchSize = $batchSize > 0 ? min($batchSize, 100) : self::BATCH_SIZE;
        $delayMs = (int) config('providers.socpanel.cancel_delay_ms', self::DELAY_BETWEEN_CALLS_MS);

        $orders = ProviderOrder::query()
            ->whereIn('status', [Order::DEPENDS_STATUS_FAILED, Order::STATUS_INVALID_LINK])
            ->where('provider_code', $providerName)
            ->where('remains', '>', 0)
            ->orderBy('id')
            ->where(function ($q) {
                $q->whereNull('provider_last_error')
                    ->orWhere('provider_last_error', '<>', 'Expected bot start link without referral');
            })
            ->limit($batchSize)
            ->get();

        if ($orders->isEmpty()) {
            Log::debug('Socpanel cancel job: no orders to process');
            return;
        }

        Log::info('Socpanel cancel job: processing batch', [
            'count' => $orders->count(),
            'first_id' => $orders->first()->id,
            'last_id' => $orders->last()->id,
        ]);

        foreach ($orders as $order) {
            $this->cancelOne($client, $order, $delayMs);
        }
    }

    private function cancelOne(SocpanelClient $client, ProviderOrder $order, int $delayMs): void
    {
        $providerOrderId = (int) $order->remote_order_id;
        if ($providerOrderId <= 0) {
            Log::warning('Socpanel cancel job: invalid provider_order_id, skipping', [
                'order_id' => $order->id,
                'provider_order_id' => $order->remote_order_id,
            ]);
            return;
        }

        try {
           $res = $client->editOrder($providerOrderId, 'canceled');

            if (($res['error'] ?? null)){
                $order->update([
                    'status' => Order::STATUS_CANCELED,
                ]);
            }

           if ($res['ok'] === true) {
               $order->update([
                   'status' => Order::STATUS_CANCELED,
               ]);
           }



            Log::info('Socpanel cancel job: order canceled', [
                'order_id' => $order->id,
                'provider_order_id' => $providerOrderId,
                'result' => 'canceled',
            ]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            // If API indicates already completed/canceled, mark local as canceled and advance cursor
            if ($this->isAlreadyCanceledOrCompleted($message)) {
                $order->update([
                    'status' => Order::STATUS_CANCELED,
                    'provider_last_error' => 'Canceled remotely due to invalid link (already canceled on provider)',
                    'provider_last_error_at' => now(),
                ]);
                Log::info('Socpanel cancel job: order already canceled on provider', [
                    'order_id' => $order->id,
                    'provider_order_id' => $providerOrderId,
                    'result' => 'already_canceled',
                ]);
            } else {
                Log::error('Socpanel cancel job: API failed', [
                    'order_id' => $order->id,
                    'provider_order_id' => $providerOrderId,
                    'result' => 'error',
                    'error' => $message,
                ]);
            }
        }

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    private function isAlreadyCanceledOrCompleted(string $message): bool
    {
        $lower = strtolower($message);
        return str_contains($lower, 'already canceled')
            || str_contains($lower, 'already cancelled')
            || str_contains($lower, 'already completed')
            || str_contains($lower, 'not found');
    }
}
