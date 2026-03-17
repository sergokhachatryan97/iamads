<?php

namespace App\Jobs;

use App\Models\ProviderOrder;
use App\Models\ProviderService;
use App\Services\Providers\SocpanelClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class SyncCompletedProviderOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public array $backoff = [60, 120, 300];

    private const LOCK_KEY = 'jobs:sync-completed-provider-orders';
    private const LOCK_TTL = 900;

    private const LAST_SEEN_KEY_PREFIX = 'socpanel:sync-completed:last_seen_id:';
    private const LAST_SEEN_TTL_DAYS = 30;

    private const PAGE_LIMIT = 50;

    public function handle(SocpanelClient $client): void
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);
        if (! $lock->get()) {
            Log::debug('SyncCompletedProviderOrdersJob: skipped, lock held');
            return;
        }

        try {
            $providerCode = $this->getProviderCode();
            $providerServices = $this->getProviderServices();
            $todayStart = now()->startOfDay();

            if ($providerServices === []) {
                Log::info('SyncCompletedProviderOrdersJob: no active provider services found', [
                    'provider_code' => $providerCode,
                ]);
                return;
            }

            $totalCreated = 0;
            $totalUpdated = 0;
            $totalSeenIds = 0;

            foreach ($providerServices as $providerServiceId) {
                $lastSeenKey = $this->getLastSeenCacheKey($providerServiceId);
                $lastSeenId = Cache::get($lastSeenKey);

                $created = 0;
                $updated = 0;
                $seenIds = 0;
                $newestId = null;
                $page = null;

                try {
                    $page = $client->getCompletedOrdersPage(
                        0,
                        self::PAGE_LIMIT,
                        $providerServiceId
                    );
                } catch (ConnectionException $e) {
                    Log::warning('SyncCompletedProviderOrdersJob: Socpanel API connection failed, job will retry', [
                        'message' => $e->getMessage(),
                        'provider_service_id' => $providerServiceId,
                    ]);
                    throw $e;
                }

                $items = $page['items'] ?? [];

                if (! is_array($items) || $items === []) {
                    Log::info('SyncCompletedProviderOrdersJob: no completed items returned for service', [
                        'provider_code' => $providerCode,
                        'provider_service_id' => $providerServiceId,
                    ]);
                    continue;
                }

                $existingMap = $this->loadExistingMap($providerCode, $items);

                $result = $this->processItems(
                    items: $items,
                    providerCode: $providerCode,
                    existingMap: $existingMap,
                    lastSeenId: $lastSeenId,
                    todayStart: $todayStart
                );

                $created += $result['created'];
                $updated += $result['updated'];
                $seenIds += $result['seen_ids'];
                $newestId = $result['newest_id'];

                if ($newestId !== null) {
                    Cache::put(
                        $lastSeenKey,
                        (string) $newestId,
                        now()->addDays(self::LAST_SEEN_TTL_DAYS)
                    );
                }

                $totalCreated += $created;
                $totalUpdated += $updated;
                $totalSeenIds += $seenIds;

                Log::info('SyncCompletedProviderOrdersJob: service run summary', [
                    'provider_code' => $providerCode,
                    'provider_service_id' => $providerServiceId,
                    'total_in_api' => $page['count'] ?? null,
                    'seen_ids' => $seenIds,
                    'created' => $created,
                    'updated' => $updated,
                    'last_seen_old' => $lastSeenId,
                    'last_seen_new' => $newestId,
                    'stopped_by_last_seen' => $result['reached_last_seen'],
                    'stopped_before_today' => $result['reached_before_today'],
                ]);
            }

            Log::info('SyncCompletedProviderOrdersJob: full run summary', [
                'provider_code' => $providerCode,
                'services_count' => count($providerServices),
                'seen_ids' => $totalSeenIds,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncCompletedProviderOrdersJob: failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // no-op
            }
        }
    }

    private function getProviderCode(): string
    {
        return (string) config('providers.socpanel.code', 'adtag');
    }

    /**
     * @return array<int, string>
     */
    private function getProviderServices(): array
    {
        return ProviderService::query()
            ->where('provider_code', $this->getProviderCode())
            ->where('is_active', true)
            ->pluck('remote_service_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function getLastSeenCacheKey(string $providerServiceId): string
    {
        return self::LAST_SEEN_KEY_PREFIX . $providerServiceId;
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

    /**
     * @return array{
     *     created:int,
     *     updated:int,
     *     seen_ids:int,
     *     newest_id:?string,
     *     reached_before_today:bool,
     *     reached_last_seen:bool
     * }
     */
    private function processItems(
        array $items,
        string $providerCode,
        array $existingMap,
        ?string $lastSeenId,
        Carbon $todayStart
    ): array {
        $created = 0;
        $updated = 0;
        $seenIds = 0;
        $newestId = null;
        $reachedBeforeToday = false;
        $reachedLastSeen = false;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $remoteOrderId = (string) ($item['id'] ?? '');
            if ($remoteOrderId === '') {
                continue;
            }

            if ($newestId === null) {
                $newestId = $remoteOrderId;
            }

            if ($lastSeenId !== null && $remoteOrderId === (string) $lastSeenId) {
                $reachedLastSeen = true;
                break;
            }

            $itemDate = $this->orderDateFromItem($item);
            if ($itemDate !== null && $itemDate->lt($todayStart)) {
                $reachedBeforeToday = true;
                break;
            }

            $payload = $this->itemToPayload($item, $providerCode);
            if ($payload === null) {
                continue;
            }

            $seenIds++;

            if (isset($existingMap[$remoteOrderId])) {
                ProviderOrder::query()
                    ->whereKey((int) $existingMap[$remoteOrderId])
                    ->update($payload);

                $updated++;
            } else {
                ProviderOrder::query()->create($payload);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'seen_ids' => $seenIds,
            'newest_id' => $newestId,
            'reached_before_today' => $reachedBeforeToday,
            'reached_last_seen' => $reachedLastSeen,
        ];
    }

    /**
     * Build provider_orders attributes from API item.
     * Returns null if item is invalid.
     */
    private function itemToPayload(array $item, string $providerCode): ?array
    {
        $remoteOrderId = (string) ($item['id'] ?? '');
        if ($remoteOrderId === '') {
            return null;
        }

        $remains = isset($item['remains']) ? (int) $item['remains'] : 0;
        $quantity = array_key_exists('quantity', $item)
            ? (int) $item['quantity']
            : $remains;

        $charge = (isset($item['charge']) && is_numeric($item['charge']))
            ? (float) $item['charge']
            : 0.0;

        $user = $item['user'] ?? [];
        $userLogin = is_array($user) ? ($user['login'] ?? null) : null;
        $userRemoteId = is_array($user) && isset($user['id'])
            ? (string) $user['id']
            : null;

        $status = isset($item['status']) ? (string) $item['status'] : null;

        return [
            'provider_code' => $providerCode,
            'remote_order_id' => $remoteOrderId,
            'remote_service_id' => isset($item['service_id']) ? (string) $item['service_id'] : null,
            'link' => trim((string) ($item['link'] ?? '')) ?: null,
            'charge' => $charge,
            'start_count' => isset($item['start_count']) ? (int) $item['start_count'] : null,
            'remains' => $remains,
            'quantity' => $quantity,
            'currency' => isset($item['currency']) ? (string) $item['currency'] : null,
            'user_login' => $userLogin !== null && $userLogin !== '' ? (string) $userLogin : null,
            'user_remote_id' => $userRemoteId,
            'provider_payload' => $item,
            'fetched_at' => now(),
            'status' => $status,
            'remote_status' => $status,
        ];
    }

    private function orderDateFromItem(array $item): ?Carbon
    {
        $keys = ['created_at', 'date', 'completed_at', 'updated_at', 'created'];

        foreach ($keys as $key) {
            $value = $item[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
