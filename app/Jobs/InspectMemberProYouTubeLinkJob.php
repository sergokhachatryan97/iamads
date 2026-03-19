<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ProviderOrder;
use App\Services\Providers\SocpanelClient;
use App\Services\YouTube\YouTubeInspector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InspectMemberProYouTubeLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public int $orderId) {}

    private const SERVICE_RULES = [
        28 => ['action' => 'view'],
        27 => ['action' => 'react'],
        26 => ['action' => 'comment'],
        25 => ['action' => 'share'],
        24 => ['action' => 'view'],
        23 => ['action' => 'react'],
        22 => ['action' => 'comment_like'],
        21 => ['action' => 'watch_time'],
        16 => ['action' => 'subscribe'],
    ];

    public function handle(YouTubeInspector $inspector): void
    {
        /** @var SocpanelClient $client */
        $client = app()->make(SocpanelClient::class, [
            'provider' => 'memberpro',
        ]);

        $claimed = ProviderOrder::query()
            ->whereKey($this->orderId)
            ->where('status', Order::STATUS_VALIDATING)
            ->update([
                'provider_sending_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $order = ProviderOrder::query()->find($this->orderId);

        if (!$order) {
            Log::warning('Provider order not found after claim', [
                'order_id' => $this->orderId,
            ]);
            return;
        }

        try {
            $link = trim((string) ($order->link ?? ''));

            if ($link === '') {
                $this->markFailedState($order, 'Empty link');
                return;
            }

            $inspectionResult = $inspector->inspect($link);

            if (!($inspectionResult['ok'] ?? false)) {
                $this->markFailedState(
                    $order,
                    'YouTube inspection failed',
                    $inspectionResult
                );
                return;
            }

            $rule = self::SERVICE_RULES[$order->remote_service_id] ?? null;

            if (!$rule) {
                $this->markFailedState(
                    $order,
                    'Unsupported remote_service_id: ' . $order->remote_service_id,
                    $inspectionResult
                );
                return;
            }

            $action = $rule['action'];

            $startCount = match ($action) {
                'view' => (int) ($inspectionResult['statistics']['views'] ?? 0),
                'react' => (int) ($inspectionResult['statistics']['likes'] ?? 0),
                'comment' => (int) ($inspectionResult['statistics']['comments'] ?? 0),
                'subscribe' => (int) ($inspectionResult['subscriber_count'] ?? 0),

                'share', 'comment_like', 'watch_time' => 0,

                default => 0,
            };

            $response = $client->editOrder($order->remote_order_id, null, $startCount);

            if ($response['ok'] ?? false) {
                $order->update([
                    'status' => Order::DEPENDS_STATUS_OK,
                    'start_count' => $startCount,
                    'provider_last_error' => null,
                    'provider_last_error_at' => null,
                    'provider_sending_at' => null,
                    'provider_payload' => $inspectionResult,
                ]);

                return;
            }

            $this->markFailedState(
                $order,
                $response['error'] ?? 'Provider editOrder failed',
                [
                    'inspection' => $inspectionResult,
                    'provider_response' => $response,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('InspectMemberProYouTubeLinkJob failed', [
                'order_id' => $this->orderId,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            if (isset($order)) {
                $this->markFailedState($order, $e->getMessage());
            }

            throw $e;
        }
    }

    private function markFailedState(
        ProviderOrder $order,
        string $error,
        array $payload = []
    ): void {
        $order->update([
            'provider_sending_at' => null,
            'provider_last_error' => $error,
            'provider_last_error_at' => now(),
            'provider_payload' => !empty($payload) ? $payload : $order->provider_payload,
        ]);
    }
}
