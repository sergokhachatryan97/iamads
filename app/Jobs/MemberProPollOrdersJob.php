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

class MemberProPollOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_PAGES_PER_SERVICE = 20;
    private const MAX_ITEMS_PER_SERVICE = 1500;

    private const MAX_VALIDATE_DISPATCH_PER_RUN = 250;
    private const CURSOR_TTL_SECONDS = 3600;


    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public string $status = 'active') {}

    public function handle(): void
    {
        $status = $this->status;
        $providerName = 'memberpro';

        $lockKey = "memberpro:poll:{$status}";
        $lock = Cache::lock($lockKey, 60); // was 180

        if (!$lock->get()) {
            Log::debug('Socpanel poll skipped: another poller is running', ['status' => $status]);
            return;
        }

        try {
            $serviceIds = [28, 27, 26, 25, 24, 23, 22, 21, 16];

            $validateDispatchBudget = self::MAX_VALIDATE_DISPATCH_PER_RUN;

            foreach ($serviceIds as $serviceId) {


                $this->pollServiceOrders(
                    $serviceId,
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
        int $providerServiceId,
        string $providerName,
        string $status,
        int &$validateDispatchBudget
    ): void {
        $limit = 100;
        $offset = 0;

        $client = app()->make(SocpanelClient::class, [
            'provider' => $providerName,
        ]);

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

                $dispatched = $this->upsertOrder($item, $providerName, $validateDispatchBudget);
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
        string $providerName,
        int $validateDispatchBudget
    ): bool {
        $providerOrderId = (string) ($item['id'] ?? '');
        if ($providerOrderId === '') return false;

        $rawLink = (string)($item['link'] ?? '');
        $normalizedLink = $rawLink;
        if ($normalizedLink === '') return false;

        $remains = isset($item['remains']) ? (int) $item['remains'] : 0;
        $quantity = max(0, $remains);

        $charge = isset($item['charge']) && is_numeric($item['charge']) ? $item['charge'] : 0;

        $existing = ProviderOrder::query()
            ->where('provider_code', $providerName)
            ->where('remote_order_id', $providerOrderId)
            ->first();

        $order = null;

        if (!$existing) {
            if ($remains > 0  && $item['start_count'] === 0) {
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
            }
        }

        if (!$order) return false;
        if ($validateDispatchBudget <= 0) return false;

        InspectMemberProYouTubeLinkJob::dispatch($order->id);
        return true;
    }

}
