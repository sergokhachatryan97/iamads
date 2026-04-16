<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ProviderOrder;
use App\Services\Providers\SocpanelClient;
use App\Support\Links\Inspectors\MaxLinkInspector;
use App\Support\SystemGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Polls Socpanel for Max Messenger orders (service IDs: 163,164,168,169,170,171,173,174,207).
 * Validates links using MaxLinkInspector, upserts into provider_orders.
 */
class MaxMessengerPollOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /** Max Messenger remote service IDs on Socpanel. */
    private const SERVICE_IDS = [163, 164, 168, 169, 170, 171, 173, 174, 207];

    private const PROVIDER_CODE = 'adtag';

    private const PAGE_LIMIT = 100;

    private const MAX_PAGES = 20;

    public function __construct(public string $status = 'active') {}

    public function handle(SocpanelClient $client): void
    {
        // CPU / kill-switch guard — skip when overloaded or paused.
        if (SystemGuard::shouldSkipHeavyWork("max_poll_{$this->status}")) {
            return;
        }

        // Per-provider rate limit. Note Max Messenger and Socpanel share the
        // same upstream API (adtag) — use a distinct prefix so we don't
        // conflict with the Socpanel poller's rate-limit slot.
        $minInterval = (int) config('system_guard.provider_poll_interval_seconds', 8);
        if (! SystemGuard::claim("poll:max:{$this->status}", $minInterval)) {
            return;
        }

        $lock = Cache::lock("max:poll:{$this->status}", 240);
        if (! $lock->get()) {
            return;
        }

        try {
            $inspector = new MaxLinkInspector();
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalInvalid = 0;

            foreach (self::SERVICE_IDS as $serviceId) {
                [$created, $updated, $invalid] = $this->pollService($client, $inspector, $serviceId);
                $totalCreated += $created;
                $totalUpdated += $updated;
                $totalInvalid += $invalid;
            }

            if ($totalCreated > 0 || $totalUpdated > 0) {
                Log::info('MaxMessenger poll done', [
                    'status' => $this->status,
                    'created' => $totalCreated,
                    'updated' => $totalUpdated,
                    'invalid' => $totalInvalid,
                ]);
            }
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @return array{int, int, int} [created, updated, invalid]
     */
    private function pollService(SocpanelClient $client, MaxLinkInspector $inspector, int $serviceId): array
    {
        $offset = 0;
        $pages = 0;
        $created = 0;
        $updated = 0;
        $invalid = 0;

        do {
            if ($pages >= self::MAX_PAGES) {
                break;
            }

            try {
                $response = $client->getOrders($serviceId, $this->status, self::PAGE_LIMIT, $offset);
            } catch (\Throwable $e) {
                Log::error('MaxMessenger getOrders failed', [
                    'service_id' => $serviceId,
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            $items = is_array($response['items'] ?? null) ? $response['items'] : [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $result = $this->upsertOrder($item, $inspector, $serviceId);

                match ($result) {
                    'created' => $created++,
                    'updated' => $updated++,
                    'invalid' => $invalid++,
                    default => null,
                };
            }

            if (count($items) < self::PAGE_LIMIT) {
                break;
            }

            $offset += self::PAGE_LIMIT;
            $pages++;
        } while (true);

        return [$created, $updated, $invalid];
    }

    /**
     * @return string 'created'|'updated'|'invalid'|'skipped'
     */
    private function upsertOrder(array $item, MaxLinkInspector $inspector, int $serviceId): string
    {
        $remoteOrderId = (string) ($item['id'] ?? '');
        if ($remoteOrderId === '') {
            return 'skipped';
        }

        $rawLink = trim((string) ($item['link'] ?? ''));
        if ($rawLink === '') {
            return 'skipped';
        }

        // Validate Max Messenger link
        $inspection = $inspector->inspect($rawLink);
        if (! ($inspection['valid'] ?? false)) {
            $errorMsg = $inspection['error'] ?? 'Invalid Max Messenger link';

            // Upsert as invalid — SocpanelCancelInvalidOrderJob will cancel it on Socpanel
            ProviderOrder::query()->updateOrCreate(
                ['provider_code' => self::PROVIDER_CODE, 'remote_order_id' => $remoteOrderId],
                [
                    'remote_service_id' => $serviceId,
                    'link' => $rawLink,
                    'remains' => max(0, (int) ($item['remains'] ?? 0)),
                    'quantity' => max(0, (int) ($item['remains'] ?? 0)),
                    'delivered' => 0,
                    'status' => Order::DEPENDS_STATUS_FAILED,
                    'provider_last_error' => $errorMsg,
                    'provider_last_error_at' => now(),
                    'provider_payload' => $item,
                    'fetched_at' => now(),
                    'remote_status' => $item['status'] ?? null,
                ]
            );

            return 'invalid';
        }

        $remains = max(0, (int) ($item['remains'] ?? 0));
        $charge = is_numeric($item['charge'] ?? null) ? $item['charge'] : 0;

        $existing = ProviderOrder::query()
            ->where('provider_code', self::PROVIDER_CODE)
            ->where('remote_order_id', $remoteOrderId)
            ->first();

        if (! $existing) {
            if ($remains <= 0) {
                return 'skipped';
            }

            ProviderOrder::create([
                'provider_code' => self::PROVIDER_CODE,
                'remote_order_id' => $remoteOrderId,
                'remote_service_id' => $item['service_id'] ?? $serviceId,
                'link' => $rawLink,
                'currency' => $item['currency'] ?? null,
                'charge' => $charge,
                'start_count' => (int) ($item['start_count'] ?? 0),
                'user_login' => $item['user']['login'] ?? null,
                'user_remote_id' => $item['user']['id'] ?? null,
                'quantity' => $remains,
                'remains' => $remains,
                'delivered' => 0,
                'provider_payload' => $item,
                'fetched_at' => now(),
                'status' => Order::DEPENDS_STATUS_OK,
                'remote_status' => $item['status'] ?? null,
            ]);

            return 'created';
        }

        // Update existing — only if still validating
        if ($existing->status === Order::DEPENDS_STATUS_OK) {
            $existing->update([
                'link' => $rawLink,
                'charge' => $charge,
                'remains' => $remains,
                'start_count' => (int) ($item['start_count'] ?? 0),
                'provider_payload' => $item,
                'remote_status' => $item['status'] ?? null,
                'status' =>  Order::DEPENDS_STATUS_OK,
            ]);

            return 'updated';
        }

        return 'skipped';
    }
}
