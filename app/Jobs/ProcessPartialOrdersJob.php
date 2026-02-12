<?php

namespace App\Jobs;

use App\Models\ProviderOrder;
use App\Services\Providers\SocpanelClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fetches partial orders from Socpanel API and saves them into provider_orders.
 * Stores statistics for dashboard display. Schedule hourly via Laravel scheduler.
 */
class ProcessPartialOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public array $backoff = [60, 120];

    private const CACHE_KEY = 'stats:partial_orders';
    private const CACHE_TTL_SECONDS = 7200;
    private const LOCK_KEY = 'jobs:process-partial-orders';
    private const LOCK_TTL = 540;
    private const PAGE_LIMIT = 100;
    private const MAX_PAGES_PER_RUN = 50;

    public function handle(SocpanelClient $client): void
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);
        if (! $lock->get()) {
            Log::debug('ProcessPartialOrdersJob: skipped, lock held');
            return;
        }

        try {
            $providerCode = (string) config('providers.socpanel.code', 'adtag');
            $dateFrom = now()->subDays(30)->startOfDay()->format('Y-m-d');
            $dateTo = now()->endOfDay()->format('Y-m-d');

            $created = 0;
            $updated = 0;
            $offset = 0;
            $pages = 0;

            while ($pages < self::MAX_PAGES_PER_RUN) {
                try {
                    $page = $client->getOrdersByStatusPage('partial', $offset, self::PAGE_LIMIT, $dateFrom, $dateTo);
                } catch (ConnectionException $e) {
                    Log::warning('ProcessPartialOrdersJob: Socpanel API connection failed', ['message' => $e->getMessage()]);
                    throw $e;
                }

                $items = $page['items'] ?? [];
                if (! is_array($items) || $items === []) {
                    break;
                }

                $existingMap = $this->loadExistingMap($providerCode, $items);
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $payload = $this->itemToPayload($item, $providerCode);
                    if ($payload === null) {
                        continue;
                    }
                    $remoteOrderId = $payload['remote_order_id'];
                    if (isset($existingMap[$remoteOrderId])) {
                        ProviderOrder::query()->whereKey((int) $existingMap[$remoteOrderId])->update($payload);
                        $updated++;
                    } else {
                        ProviderOrder::query()->create($payload);
                        $created++;
                    }
                }

                $offset += count($items);
                $pages++;
                if (! ($page['has_more'] ?? false)) {
                    break;
                }
            }

            $stats = ProviderOrder::query()
                ->where('provider_code', $providerCode)
                ->where(function ($q) {
                    $q->where('remote_status', 'partial')->orWhere('status', 'partial');
                })
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(charge), 0) as total_charge, COALESCE(SUM(remains), 0) as total_remains')
                ->first();

            $data = [
                'count' => (int) ($stats?->count ?? 0),
                'total_charge' => (float) ($stats?->total_charge ?? 0),
                'total_remains' => (int) ($stats?->total_remains ?? 0),
                'last_run_at' => now()->toIso8601String(),
                'created' => $created,
                'updated' => $updated,
            ];

            Cache::put(self::CACHE_KEY, $data, self::CACHE_TTL_SECONDS);

            Log::info('ProcessPartialOrdersJob: run summary', $data);
        } catch (\Throwable $e) {
            Log::error('ProcessPartialOrdersJob: failed', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @return array<string, int> remote_order_id => id
     */
    private function loadExistingMap(string $providerCode, array $items): array
    {
        $ids = [];
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $id = (string) ($it['id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        return ProviderOrder::query()
            ->where('provider_code', $providerCode)
            ->whereIn('remote_order_id', $ids)
            ->pluck('id', 'remote_order_id')
            ->all();
    }

    private function itemToPayload(array $item, string $providerCode): ?array
    {
        $remoteOrderId = (string) ($item['id'] ?? '');
        if ($remoteOrderId === '') {
            return null;
        }

        // API: remains/charge can be string ("10", "0")
        $remains = isset($item['remains']) ? (int) $item['remains'] : 0;
        $quantity = array_key_exists('quantity', $item) ? (int) $item['quantity'] : $remains;
        $charge = isset($item['charge']) && (is_numeric($item['charge']) || $item['charge'] === '') ? (float) $item['charge'] : 0.0;

        $user = $item['user'] ?? [];
        $userLogin = is_array($user) ? ($user['login'] ?? null) : null;
        $userRemoteId = is_array($user) && array_key_exists('id', $user) ? (string) $user['id'] : null;

        return [
            'provider_code' => $providerCode,
            'remote_order_id' => $remoteOrderId,
            'remote_service_id' => isset($item['service_id']) ? (string) $item['service_id'] : null,
            'link' => isset($item['link']) && trim((string) $item['link']) !== '' ? trim((string) $item['link']) : null,
            'charge' => $charge,
            'start_count' => array_key_exists('start_count', $item) ? (int) $item['start_count'] : null,
            'remains' => $remains,
            'quantity' => $quantity,
            'currency' => isset($item['currency']) ? (string) $item['currency'] : null,
            'user_login' => $userLogin !== null && $userLogin !== '' ? (string) $userLogin : null,
            'user_remote_id' => $userRemoteId,
            'provider_payload' => $item,
            'fetched_at' => now(),
            'status' => isset($item['status']) ? (string) $item['status'] : null,
            'remote_status' => isset($item['status']) ? (string) $item['status'] : null,
        ];
    }
}
