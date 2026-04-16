<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ProviderOrder;
use App\Models\ProviderService;
use App\Services\Providers\SocpanelClient;
use App\Support\SystemGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SocpanelPollOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_PAGES_PER_SERVICE = 20;
    private const MAX_ITEMS_PER_SERVICE = 1500;
    private const CURSOR_TTL_SECONDS = 3600;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public string $status = 'active') {}

    public function handle(SocpanelClient $client): void
    {
        $status = $this->status;

        // CPU/kill-switch guard: if the system is paused or overloaded, skip
        // this run entirely. The next scheduled tick will try again. Avoids
        // piling more work onto an already-struggling server.
        if (SystemGuard::shouldSkipHeavyWork("socpanel_poll_{$status}")) {
            return;
        }

        // Per-provider rate limit (PROVIDER_POLL_INTERVAL_SECONDS). If another
        // worker polled this provider+status within the cooldown, bail out.
        $minInterval = (int) config('system_guard.provider_poll_interval_seconds', 8);
        if (! SystemGuard::claim("poll:socpanel:{$status}", $minInterval)) {
            Log::debug('Socpanel poll skipped: per-provider rate limit', [
                'status' => $status,
                'min_interval' => $minInterval,
            ]);
            return;
        }

        $lockKey = "socpanel:poll:{$status}";
        $lock = Cache::lock($lockKey, 240); // was 180

        if (!$lock->get()) {
            Log::debug('Socpanel poll skipped: another poller is running', ['status' => $status]);
            return;
        }

        try {
            $providerName = 'adtag';

            $services = ProviderService::query()
                ->where('provider_code', $providerName)
                ->where('is_active', 1)
                ->get();

            if ($services->isEmpty()) {
                Log::debug('Socpanel poll: no provider services found', ['status' => $status]);
                return;
            }

            // Configured via config/system_guard.php → env MAX_VALIDATE_DISPATCH_PER_RUN.
            // Hard cap on validate-inspection dispatches per poll run so a single
            // hot service can't flood the tg-inspect queue (which only has 2 workers).
            $validateDispatchBudget = (int) config('system_guard.max_validate_dispatch_per_run', 50);

            foreach ($services as $localService) {
                $providerServiceId = (int) $localService->remote_service_id;
                if ($providerServiceId <= 0) {
                    continue;
                }

                $this->pollServiceOrders(
                    $client,
                    $providerServiceId,
                    $localService,
                    $providerName,
                    $status,
                    $validateDispatchBudget
                );

                if ($validateDispatchBudget <= 0) {
                    Log::info('Socpanel poll: validate dispatch budget exhausted', [
                        'status' => $status,
                        'budget' => (int) config('system_guard.max_validate_dispatch_per_run', 50),
                    ]);
                    break;
                }
            }

        } finally {
            try { $lock->release(); } catch (\Throwable $e) {}
        }
    }


    private function pollServiceOrders(
        SocpanelClient $client,
        int $providerServiceId,
        ProviderService $localService,
        string $providerName,
        string $status,
        int &$validateDispatchBudget
    ): void {
        $limit = 100;
        $offset = 0;

        $pageCount = 0;
        $processed = 0;

        do {
            if ($pageCount >= self::MAX_PAGES_PER_SERVICE || $processed >= self::MAX_ITEMS_PER_SERVICE) {
                Log::info('Socpanel poll capped', [
                    'provider_service_id' => $providerServiceId,
                    'status' => $status,
                    'pages' => $pageCount,
                    'processed' => $processed,
                ]);
                break;
            }

            if ($validateDispatchBudget <= 0) {
                break;
            }

            try {
                $response = $client->getOrders($providerServiceId, $status, $limit, $offset);
            } catch (\Throwable $e) {
                Log::error('Socpanel getOrders failed', [
                    'provider_service_id' => $providerServiceId,
                    'status' => $status,
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            $items = is_array($response['items'] ?? null) ? $response['items'] : [];

            foreach ($items as $item) {
                if (!is_array($item)) continue;

                $dispatched = $this->upsertOrder($item, $localService, $providerName, $validateDispatchBudget);
                if ($dispatched) {
                    $validateDispatchBudget--;
                }

                $processed++;
                if ($processed >= self::MAX_ITEMS_PER_SERVICE) break;
                if ($validateDispatchBudget <= 0) break;
            }

            if (count($items) < $limit) {
                break;
            }

            $offset += $limit;
            $pageCount++;

        } while (true);
    }



    private function upsertOrder(
        array $item,
        ProviderService $localService,
        string $providerName,
        int $validateDispatchBudget
    ): bool {
        $providerOrderId = (string) ($item['id'] ?? '');
        if ($providerOrderId === '') return false;

        $rawLink = (string)($item['link'] ?? '');
        $normalizedLink = $this->normalizeTelegramLink($rawLink);
        if ($normalizedLink === '') return false;

        $remains = isset($item['remains']) ? (int) $item['remains'] : 0;
        $quantity = max(0, $remains);

        $charge = isset($item['charge']) && is_numeric($item['charge']) ? $item['charge'] : 0;

        $existing = ProviderOrder::query()
            ->where('provider_code', $providerName)
            ->where('remote_order_id', $providerOrderId)
            ->first();

        $wasCreated = false;
        $order = null;

        if (!$existing) {
            if ($remains > 0) {
                $order = ProviderOrder::query()->create([
                    'provider_code' => $providerName,
                    'remote_order_id' => $providerOrderId,
                    'remote_service_id' => $item['service_id'],
                    'link' => $normalizedLink,
                    'currency' => $item['currency'] ?? null,
                    'charge' => $charge,
                    'start_count' => $item['start_count'],
                    'user_login' => $item['user']['login'] ?? null,
                    'user_remote_id' => $item['user']['id'] ?? null,
                    'quantity' => $quantity,
                    'remains' => $quantity,
                    'delivered' => 0,
                    'provider_payload' => $item,
                    'fetched_at' => now(),
                    'status' => Order::STATUS_VALIDATING,
                    'remote_status' => $item['status'] ?? null,
                ]);
                $wasCreated = true;
            }
        } else {
            if ($existing->status === Order::STATUS_VALIDATING) {
                $existing->update([
                    'link' => $normalizedLink,
                    'status' => $quantity === 0 ? Order::DEPENDS_STATUS_OK : $existing->status,
                    'charge' => $charge,
                    'remains' => $quantity,
                    'start_count' => $item['start_count'],
                    'provider_payload' => $item,
                    'remote_status' => $item['status']
                ]);
            }
            $order = $existing;
            $wasCreated = false; // ✅ bug fix
        }

        if (!$order) return false;
        if ($validateDispatchBudget <= 0) return false;

        return $this->dispatchValidateIfNeeded($order, $wasCreated);
    }


    private function dispatchValidateIfNeeded(ProviderOrder $order, bool $wasCreated): bool
    {
        if ((int)$order->remains <= 0) return false;
        if ($order->status !== Order::STATUS_VALIDATING) return false;
        if (!empty($order->provider_sending_at)) return false;

        if (!$wasCreated) {
            // Defence-in-depth: skip if we hit an error recently. The primary
            // guard is `provider_sending_at` (set by the validate job on claim
            // and intentionally kept set across temporary failures so the 10-min
            // claim TTL acts as the cool-down). This extra check protects cases
            // where provider_sending_at was cleared (e.g. non-retryable failure
            // path) but the link is still producing errors.
            $last = $order->provider_last_error_at;
            if ($last && $last->gt(now()->subMinutes(5))) {
                return false;
            }
        }

        $serviceId = (int)$order->remote_service_id;
        $link      = (string)$order->link;
        $groupKey  = sha1($serviceId . '|' . $link);

        // Dedupe: do not enqueue another job for same (serviceId, link) within TTL.
        // TTL must be >= the job's worst-case runtime (timeout=120 + buffer) so
        // we never re-dispatch a (serviceId, link) while its previous job is
        // still running — that was causing duplicate MTProto inspections and
        // CPU saturation on the tg-inspect workers.
        $dedupeKey = 'tg:inspect:dispatch:' . $groupKey;
        $dedupeTtl = 150;
        if (!Cache::add($dedupeKey, 1, $dedupeTtl)) {
            return false;
        }

        $lockTtl = $wasCreated ? 150 : 90;
        $dispatchLock = Cache::lock("socpanel:validate-group-dispatch:{$groupKey}", $lockTtl);

        if (!$dispatchLock->get()) return false;

        try {
            $delaySeconds = random_int(0, 50);

            SocpanelValidateOrderJob::dispatch($serviceId, $link)
                ->onQueue('tg-inspect')
                ->delay(now()->addSeconds($delaySeconds))
                ->afterCommit();

            return true;
        } finally {
            try { $dispatchLock->release(); } catch (\Throwable $e) {}
        }
    }

    private function cursorCacheKey(int $providerServiceId, string $status): string
    {
        return "socpanel:poll:cursor:{$providerServiceId}:{$status}";
    }


    private function normalizeTelegramLink(?string $link): string
    {
       return trim((string)$link);

    }

}
