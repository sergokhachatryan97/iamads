<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Provider-style API actions (Socpanel / Perfect Panel compatible).
 * Reuses existing OrderService, PricingService, External controllers logic.
 */
class ProviderApiService
{
    public function __construct(
        private OrderServiceInterface $orderService,
        private PricingService $pricingService
    ) {}

    /**
     * Action: services – list active services for the client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function services(Client $client): array
    {
        $categories = Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->with(['services' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->get();

        $services = [];
        foreach ($categories as $category) {
            foreach ($category->services as $service) {
                if ($service->service_type === 'custom_comments') {
                    continue; // Skip custom_comments for provider API (not supported in add)
                }
                $rate = (float) $this->pricingService->priceForClient($service, $client);

                $services[] = [
                    'service' => (int) $service->id,
                    'name' => $service->name,
                    'type' => 'default',
                    'rate' => number_format($rate, 2, '.', ''),
                    'min' => (string) ($service->min_quantity ?? 1),
                    'max' => $service->max_quantity !== null ? (string) $service->max_quantity : '0',
                ];
            }
        }

        return $services;
    }

    /**
     * Action: add – create a new order.
     *
     * @return array{order: int}
     */
    public function add(Client $client, array $input): array
    {
        $serviceId = (int) ($input['service'] ?? 0);
        $link = trim((string) ($input['link'] ?? ''));
        $quantity = (int) ($input['quantity'] ?? 0);
        $speedTier = $input['speed_tier'] ?? 'normal';
        $externalOrderId = $input['order'] ?? null;
        if (!$externalOrderId || trim((string) $externalOrderId) === '') {
            $externalOrderId = 'p_' . Str::ulid();
        } else {
            $externalOrderId = (string) $externalOrderId;
        }

        $result = $this->orderService->createApiOrder($client, [
            'external_order_id' => $externalOrderId,
            'service_id' => $serviceId,
            'link' => $link,
            'quantity' => $quantity,
            'speed_tier' => $speedTier,
            'meta' => [],
        ]);

        return ['order' => (int) $result->order->id];
    }

    /**
     * Action: status – get order status.
     *
     * @return array{charge: string, start_count: string, status: string, remains: string}|null Null when order not found
     */
    public function status(Client $client, $orderIdOrExternalId): ?array
    {
        $order = $this->resolveOrderForClient($client, $orderIdOrExternalId);

        if (!$order) {
            return null;
        }

        $status = $this->mapStatusForProvider($order);
        $charge = number_format((float) $order->charge, 2, '.', '');
        $remains = (string) max(0, (int) $order->remains);
        $startCount = (string) ($order->start_count ?? '0');

        $payload = $order->provider_payload ?? [];
        if (is_array($payload) && isset($payload['youtube']['parsed']['start_counts'])) {
            $startCounts = $payload['youtube']['parsed']['start_counts'];
            if (is_array($startCounts)) {
                $startCount = (string) ($startCounts['view'] ?? $startCounts['subscribe'] ?? $order->start_count ?? 0);
            }
        }

        return [
            'charge' => $charge,
            'start_count' => $startCount,
            'status' => $status,
            'remains' => $remains,
        ];
    }

    /**
     * Action: balance – get client balance.
     *
     * @return array{balance: string, currency: string}
     */
    public function balance(Client $client): array
    {
        $balance = (float) ($client->balance ?? 0);

        return [
            'balance' => number_format($balance, 2, '.', ''),
            'currency' => 'USD',
        ];
    }

    /**
     * Resolve order by internal id or external_order_id for the client.
     */
    private function resolveOrderForClient(Client $client, $id): ?Order
    {
        $id = is_numeric($id) ? (int) $id : trim((string) $id);
        if ($id === '' && $id !== 0) {
            return null;
        }

        $query = Order::query()->where('client_id', $client->id);

        if (is_int($id) && $id > 0) {
            $query->where('id', $id)->orWhere('external_order_id', $id);
        } else {
            $query->where(function ($q) use ($id) {
                $q->where('external_order_id', $id)->orWhere('provider_order_id', $id);
            });
        }

        return $query->first();
    }

    /**
     * Map internal order status to provider-compatible status.
     */
    private function mapStatusForProvider(Order $order): string
    {
        $remains = (int) $order->remains;
        $isDone = $remains <= 0 || $order->status === Order::STATUS_COMPLETED;
        $status = $order->status;

        if ($remains <= 0 || $isDone) {
            return 'Completed';
        }
        if ($status === Order::STATUS_CANCELED) {
            return 'Canceled';
        }
        if (in_array($status, [
            Order::STATUS_INVALID_LINK,
            Order::STATUS_RESTRICTED,
            Order::STATUS_FAIL,
        ], true)) {
            return 'Failed';
        }
        if ($status === Order::STATUS_VALIDATING) {
            return 'Processing';
        }
        if (in_array($status, [
            Order::STATUS_AWAITING,
            Order::STATUS_PENDING,
            Order::STATUS_PENDING_DEPENDENCY,
        ], true)) {
            return 'Pending';
        }
        if (in_array($status, [
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_PROCESSING,
            Order::STATUS_PARTIAL,
        ], true)) {
            return 'In progress';
        }

        return $order->delivered > 0 ? 'In progress' : 'Pending';
    }
}
