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
use Illuminate\Support\Facades\Log;

/**
 * Cancels invalid provider orders on Socpanel when local orders are invalid_link or restricted.
 * Processes in small batches. Safe to run repeatedly (idempotent).
 */
class SocpanelCancelInvalidOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(SocpanelClient $client): void
    {
        $token = config('providers.socpanel.token');
        if (empty($token)) {
            throw new \RuntimeException('Socpanel cancel job: SOCPANEL_TOKEN not configured.');
        }

        $providerName = config('providers.socpanel.name', 'adtag');

        $orders = ProviderOrder::query()
            ->where('provider_code', $providerName)
            ->whereIn('status', [Order::DEPENDS_STATUS_FAILED, Order::STATUS_INVALID_LINK])
            ->where('remains', '>', 0)
            ->where(function ($q) {
                $q->whereNull('provider_last_error')
                    ->orWhere('provider_last_error', '<>', 'Expected bot start link without referral');
            })
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $canceled = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $ok = $this->cancelOne($client, $order);
            $ok ? $canceled++ : $failed++;
        }

        Log::info('Socpanel cancel job done', [
            'total' => $orders->count(),
            'canceled' => $canceled,
            'failed' => $failed,
        ]);
    }

    private function cancelOne(SocpanelClient $client, ProviderOrder $order): bool
    {
        $providerOrderId = (int) $order->remote_order_id;
        if ($providerOrderId <= 0) {
            Log::warning('Socpanel cancel: invalid remote_order_id', ['order_id' => $order->id]);

            return false;
        }

        try {
            $res = $client->editOrder($providerOrderId, 'canceled');

            // API returned success — mark canceled
            if (($res['ok'] ?? false) === true) {
                $order->update(['status' => Order::STATUS_CANCELED]);

                return true;
            }

            // API returned an error response (not exception) — still might mean already canceled
            $errorMsg = (string) ($res['error'] ?? '');
            if ($errorMsg !== '' && $this->isAlreadyCanceledOrCompleted($errorMsg)) {
                $order->update([
                    'status' => Order::STATUS_CANCELED,
                    'provider_last_error' => $errorMsg,
                    'provider_last_error_at' => now(),
                ]);

                return true;
            }

            Log::warning('Socpanel cancel: unexpected response', [
                'order_id' => $order->id,
                'remote_order_id' => $providerOrderId,
                'response' => $res,
            ]);

            $order->update([
                'provider_last_error' => 'Unexpected response: ' . json_encode($res),
                'provider_last_error_at' => now(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if ($this->isAlreadyCanceledOrCompleted($message)) {
                $order->update([
                    'status' => Order::STATUS_CANCELED,
                    'provider_last_error' => $message,
                    'provider_last_error_at' => now(),
                ]);

                return true;
            }

            Log::error('Socpanel cancel: API failed', [
                'order_id' => $order->id,
                'remote_order_id' => $providerOrderId,
                'error' => $message,
            ]);

            $order->update([
                'provider_last_error' => $message,
                'provider_last_error_at' => now(),
            ]);

            return false;
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
