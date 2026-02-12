<?php

namespace App\Jobs;

use App\Models\ProviderOrder;
use App\Services\Providers\SocpanelClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Syncs completed orders from Socpanel API into provider_orders.
 * API returns { count, items } with offset/limit pagination; items have id, charge, remains, status, service_id, user.{id,login}, etc.
 */
class SyncCompletedProviderOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public array $backoff = [60, 120, 300];

    private const LOCK_KEY = 'jobs:sync-completed-provider-orders';
    private const LOCK_TTL = 900;
    private const LAST_SEEN_KEY = 'socpanel:sync-completed:last_seen_id';
    private const LAST_SEEN_TTL_DAYS = 30;
    private const PAGE_LIMIT = 100;
    private const MAX_PAGES_PER_RUN = 100;

    public function handle(SocpanelClient $client): void
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);
        if (! $lock->get()) {
            Log::debug('SyncCompletedProviderOrdersJob: skipped, lock held');
            return;
        }

        try {
            $providerCode = $this->getProviderCode();
            $lastSeenId = Cache::get(self::LAST_SEEN_KEY);
            $dateFrom = now()->startOfDay()->format('Y-m-d');
            $dateTo = now()->endOfDay()->format('Y-m-d');

            $pages = 0;
            $created = 0;
            $updated = 0;
            $seenIds = 0;
            $offset = 0;
            $newestId = null;
            $lastPage = null;

            while ($pages < self::MAX_PAGES_PER_RUN) {
                try {
                    $page = $client->getCompletedOrdersPage($offset, self::PAGE_LIMIT, $dateFrom, $dateTo);
                } catch (ConnectionException $e) {
                    Log::warning('SyncCompletedProviderOrdersJob: Socpanel API connection failed (timeout or unreachable), job will retry', [
                        'message' => $e->getMessage(),
                        'offset' => $offset,
                    ]);
                    throw $e;
                }
                $lastPage = $page;
                $pages++;

                $items = $page['items'] ?? [];
                if (! is_array($items) || $items === []) {
                    break;
                }

                $existingMap = $this->loadExistingMap($providerCode, $items);
                $result = $this->processItems($items, $providerCode, $existingMap, $lastSeenId, now()->startOfDay());

                $created += $result['created'];
                $updated += $result['updated'];
                $seenIds += $result['seen_ids'];
                if ($result['newest_id'] !== null) {
                    $newestId = $result['newest_id'];
                }
                if ($result['reached_before_today']) {
                    break;
                }

                $offset += count($items);
                if (! ($page['has_more'] ?? false)) {
                    break;
                }
            }

            if ($newestId !== null) {
                Cache::put(self::LAST_SEEN_KEY, (string) $newestId, now()->addDays(self::LAST_SEEN_TTL_DAYS));
            }

            Log::info('SyncCompletedProviderOrdersJob: run summary', [
                'pages_attempted' => $pages,
                'total_in_api' => $lastPage['count'] ?? null,
                'seen_ids' => $seenIds,
                'created' => $created,
                'updated' => $updated,
                'last_seen_old' => $lastSeenId,
                'last_seen_new' => $newestId,
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
            }
        }
    }

    private function getProviderCode(): string
    {
        return (string) config('providers.socpanel.code', 'adtag');
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
     * @return array{created: int, updated: int, seen_ids: int, newest_id: ?string, reached_before_today: bool}
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

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $remoteOrderId = (string) ($item['id'] ?? '');
            if ($remoteOrderId === '') {
                continue;
            }

            $itemDate = $this->orderDateFromItem($item);
            if ($itemDate !== null && $itemDate->lt($todayStart)) {
                $reachedBeforeToday = true;
                break;
            }

            if ($newestId === null) {
                $newestId = $remoteOrderId;
            }

            if ($lastSeenId !== null && $remoteOrderId === (string) $lastSeenId) {
                continue;
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
        ];
    }

    /**
     * Build provider_orders attributes from API item. Returns null if item is invalid.
     * API fields: id, charge (string), start_count, status, remains (string), currency, service_id, user.{id, login}
     */
    private function itemToPayload(array $item, string $providerCode): ?array
    {
        $remoteOrderId = (string) ($item['id'] ?? '');
        if ($remoteOrderId === '') {
            return null;
        }

        $remains = isset($item['remains']) ? (int) $item['remains'] : 0;
        $quantity = array_key_exists('quantity', $item) ? (int) $item['quantity'] : $remains;
        $charge = (isset($item['charge']) && is_numeric($item['charge']))
            ? (float) $item['charge']
            : 0.0;

        $user = $item['user'] ?? [];
        $userLogin = is_array($user) ? ($user['login'] ?? null) : null;
        $userRemoteId = is_array($user) && isset($user['id']) ? (string) $user['id'] : null;

        return [
            'provider_code' => 'adtag',
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
            'status' => isset($item['status']) ? (string) $item['status'] : null,
            'remote_status' => isset($item['status']) ? (string) $item['status'] : null,
        ];
    }

    private function orderDateFromItem(array $item): ?Carbon
    {
        $keys = ['created_at', 'date', 'completed_at', 'updated_at', 'created'];
        foreach ($keys as $key) {
            $v = $item[$key] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            try {
                return Carbon::parse($v);
            } catch (\Throwable) {
                continue;
            }
        }
        return null;
    }
}
