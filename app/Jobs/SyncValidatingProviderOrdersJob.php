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

class SyncValidatingProviderOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public array $backoff = [60, 120, 300];

    private const BATCH_SIZE = 50;

    /** providerStatus(lowercase) => localStatus */
    private const STATUS_MAP = [
        'completed' => 'ok',
        'canceled'  => 'canceled',
    ];

    public function handle(SocpanelClient $client): void
    {
        Log::info('SyncValidatingProviderOrdersJob: started');

        $lock = Cache::lock('jobs:sync-validating-provider-orders', 240);
        if (!$lock->get()) {
            Log::debug('SyncValidatingProviderOrdersJob: skipped, lock held');
            return;
        }

        try {
            $orders = ProviderOrder::query()
                ->select(['id', 'remote_order_id', 'status', 'provider_payload'])
                ->where(function ($q) {
                    $q->where('status', Order::STATUS_VALIDATING)
                        ->orWhere(function ($q) {
                            $q->where('status', Order::DEPENDS_STATUS_FAILED)
                                ->where('remote_service_id', 76);
                        });
                })->whereNotNull('remote_order_id')
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->get();

            if ($orders->isEmpty()) {
                Log::info('SyncValidatingProviderOrdersJob: no validating orders, nothing to do');
                return;
            }

            $remoteIds = $orders
                ->pluck('remote_order_id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            if ($remoteIds === []) {
                return;
            }

            try {
                // expected: [remoteId => payload]
                $byId = $client->getOrdersByIds($remoteIds);
            } catch (\Throwable $e) {
                Log::warning('SyncValidatingProviderOrdersJob: getOrdersByIds failed', [
                    'remote_ids_count' => count($remoteIds),
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $updated = 0;

            foreach ($orders as $order) {
                $remoteId = (int) $order->remote_order_id;
                $item = $byId[$remoteId] ?? null;

                if (!$item) {
                    continue;
                }

                $providerStatus = strtolower((string) ($item['status'] ?? ''));
                $newStatus = self::STATUS_MAP[$providerStatus] ?? null;

                if ($newStatus === null) {
                    continue;
                }

                $order->update([
                    'status' => $newStatus,
                    'provider_payload' => array_merge($order->provider_payload ?? [], $item),
                ]);

                $updated++;

                Log::debug('SyncValidatingProviderOrdersJob: updated provider order status', [
                    'provider_order_id' => $order->id,
                    'remote_order_id' => $remoteId,
                    'new_status' => $newStatus,
                ]);
            }

            Log::info('SyncValidatingProviderOrdersJob: run summary', [
                'batch_size' => self::BATCH_SIZE,
                'remote_ids_count' => count($remoteIds),
                'orders_loaded' => $orders->count(),
                'updated_count' => $updated,
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
