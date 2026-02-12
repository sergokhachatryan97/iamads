<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ProviderOrder;
use App\Models\ProviderService;
use App\Services\Providers\SocpanelClient;
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

    private const MAX_VALIDATE_DISPATCH_PER_RUN = 250;
    private const CURSOR_TTL_SECONDS = 3600;


    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public string $status = 'active') {}

    public function handle(SocpanelClient $client): void
    {
        $status = $this->status;

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

            $validateDispatchBudget = self::MAX_VALIDATE_DISPATCH_PER_RUN;

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
                        'budget' => self::MAX_VALIDATE_DISPATCH_PER_RUN,
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
                // ✅ Քո իրական signature-ին համապատասխան
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

            // ✅ stop condition: եթե քիչ item եկավ, վերջ
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


    private function dispatchValidate(ProviderOrder $order): void
    {
        if ($order->remains <= 0) return;
        if ($order->status !== Order::STATUS_VALIDATING) return;

        $groupKey = sha1($order->remote_service_id . '|' . $order->remote_order_id);
        $lock = Cache::lock("socpanel:validate:{$groupKey}", 180);
        Log::info('lock', ['lock' => $lock, 'order' => $order->remote_order_id]);
        if (!$lock->get()) return;

        try {
            $delay = hexdec(substr($groupKey, 0, 2)) % 120;

            SocpanelValidateOrderJob::dispatch(
                $order->remote_service_id,
                $order->link
            )
                ->onQueue('tg-inspect')
                ->delay(now()->addSeconds($delay))
                ->afterCommit();

        } finally {
            $lock->release();
        }
    }


    private function dispatchValidateIfNeeded(ProviderOrder $order, bool $wasCreated): bool
    {
        if ((int)$order->remains <= 0) return false;
        if ($order->status !== Order::STATUS_VALIDATING) return false;
        if (!empty($order->provider_sending_at)) return false;

        if (!$wasCreated) {
            $last = $order->provider_last_error_at;
            if ($last && $last->gt(now()->subSeconds(30))) {
                return false;
            }
        }

        $serviceId = (int)$order->remote_service_id;
        $link      = (string)$order->link;
        $groupKey  = sha1($serviceId . '|' . $link);

        // Dedupe: do not enqueue another job for same (serviceId, link) within TTL
        $dedupeKey = 'tg:inspect:dispatch:' . $groupKey;
        $dedupeTtl = 90;
        if (!Cache::add($dedupeKey, 1, $dedupeTtl)) {
            return false;
        }

        $lockTtl = $wasCreated ? 90 : 45; // ✅ a bit longer, less spam
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
