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
            $result = $this->cancelOne($client, $order);

            if ($result === 'rate_limited') {
                Log::warning('Socpanel cancel: rate limited (429), stopping batch', [
                    'processed' => $canceled + $failed,
                    'remaining' => $orders->count() - $canceled - $failed,
                ]);
                break;
            }

            $result === 'ok' ? $canceled++ : $failed++;

            // 300ms between calls to avoid 429
            usleep(300_000);
        }

        if ($canceled > 0 || $failed > 0) {
            Log::info('Socpanel cancel job done', [
                'total' => $canceled + $failed,
                'canceled' => $canceled,
                'failed' => $failed,
            ]);
        }
    }

    /**
     * @return string 'ok'|'failed'|'rate_limited'
     */
    private function cancelOne(SocpanelClient $client, ProviderOrder $order): string
    {
        $providerOrderId = (int) $order->remote_order_id;
        if ($providerOrderId <= 0) {
            return 'failed';
        }

        try {
            $res = $client->editOrder($providerOrderId, 'canceled');

            if (($res['ok'] ?? false) === true) {
                $order->update(['status' => Order::STATUS_CANCELED]);

                return 'ok';
            }

            $errorMsg = (string) ($res['error'] ?? '');
            if ($errorMsg !== '' && $this->isAlreadyCanceledOrCompleted($errorMsg)) {
                $order->update([
                    'status' => Order::STATUS_CANCELED,
                    'provider_last_error' => $errorMsg,
                    'provider_last_error_at' => now(),
                ]);

                return 'ok';
            }

            $order->update([
                'provider_last_error' => 'Unexpected response: ' . substr(json_encode($res), 0, 200),
                'provider_last_error_at' => now(),
            ]);

            return 'failed';
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            // 429 Too Many Attempts — stop the entire batch immediately
            if ($this->isRateLimited($message)) {
                return 'rate_limited';
            }

            if ($this->isAlreadyCanceledOrCompleted($message)) {
                $order->update([
                    'status' => Order::STATUS_CANCELED,
                    'provider_last_error' => $message,
                    'provider_last_error_at' => now(),
                ]);

                return 'ok';
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

            return 'failed';
        }
    }

    private function isRateLimited(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, '429')
            || str_contains($lower, 'too many')
            || str_contains($lower, 'rate limit');
    }

    private function isAlreadyCanceledOrCompleted(string $message): bool
    {
        $lower = strtolower($message);
        return str_contains($lower, 'already canceled')
            || str_contains($lower, 'already cancelled')
            || str_contains($lower, 'already_canceled')
            || str_contains($lower, 'already completed')
            || str_contains($lower, 'not found');
    }
}
