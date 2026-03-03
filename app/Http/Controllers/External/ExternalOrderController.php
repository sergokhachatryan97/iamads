<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\External\ExternalOrderStoreRequest;
use App\Jobs\InspectTelegramLinkJob;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * External Orders API: subscription/join progress only.
 * Status glossary (external):
 * - validating: created and being validated (link inspection / rules)
 * - awaiting: validated and waiting to start execution
 * - in_progress: execution is running; progress shows how much is completed
 * - canceled: canceled by system or admin; may remain partially completed
 * - completed: fully completed (subscription delivery reached target)
 * - failed: failed validation or unrecoverable error
 * Unsubscribe phase is internal; never exposed to external clients.
 */
class ExternalOrderController extends Controller
{
    /**
     * Create an order (idempotent by provider + provider_order_id).
     */
    public function store(ExternalOrderStoreRequest $request): JsonResponse
    {
        $clientName = (string) $request->attributes->get('external_client');
        $externalOrderId = $request->validated('external_order_id');
        $serviceKey = $request->validated('service_key');
        $serviceId = $request->validated('service_id');
        $link = $request->validated('link');
        $quantity = (int) $request->validated('quantity');
        $speedTier = $request->validated('speed_tier');
        $meta = $request->validated('meta') ?? [];

        $clientId = config('services.external_clients.default_client_id');
        if (empty($clientId)) {
            return response()->json([
                'ok' => false,
                'error' => 'External client authentication not configured',
            ], 500);
        }

        $service = $this->resolveService($serviceKey, $serviceId);

        if (!$service) {
            return response()->json([
                'ok' => false,
                'error' => 'Service not found',
            ], 422);
        }

        $resolvedServiceKey = $serviceKey ?? (string) $service->template_key;

        return DB::transaction(function () use (
            $clientName,
            $externalOrderId,
            $resolvedServiceKey,
            $service,
            $link,
            $quantity,
            $speedTier,
            $meta,
            $clientId
        ): JsonResponse {
            $existing = Order::query()
                ->where('provider', $clientName)
                ->where('provider_order_id', $externalOrderId)
                ->first();

            if ($existing) {
                return response()->json([
                    'ok' => true,
                    'order' => $this->presentOrder($existing),
                    'duplicate' => true,
                ]);
            }

            $order = Order::create([
                'client_id' => $clientId,
                'created_by' => null,
                'category_id' => $service->category_id,
                'service_id' => $service->id,
                'link' => $link,
                'payment_source' => Order::PAYMENT_SOURCE_BALANCE,
                'subscription_id' => null,
                'charge' => 0,
                'cost' => 0,
                'quantity' => $quantity,
                'delivered' => 0,
                'remains' => Order::computeTargetQuantity($quantity, $service),
                'status' => Order::STATUS_VALIDATING,
                'mode' => Service::MODE_MANUAL,
                'provider' => $clientName,
                'provider_order_id' => $externalOrderId,
                'execution_phase' => Order::EXECUTION_PHASE_RUNNING,
                'speed_tier' => $speedTier,
                'provider_payload' => [
                    'external_order_id' => $externalOrderId,
                    'service_key' => $resolvedServiceKey,
                    'speed_tier' => $speedTier,
                    'meta' => $meta,
                ],
            ]);

            Log::info('External order created', [
                'order_id' => $order->id,
                'external_client' => $clientName,
                'external_order_id' => $externalOrderId,
            ]);

            InspectTelegramLinkJob::dispatch($order->id)
                ->onQueue('tg-external-inspect')
                ->afterCommit();

            return response()->json([
                'ok' => true,
                'order' => $this->presentOrder($order),
            ], 201);
        });
    }

    /**
     * List orders for the authenticated external client (cursor pagination).
     */
    public function index(Request $request): JsonResponse
    {
        $clientName = (string) $request->attributes->get('external_client');

        $limit = (int) $request->input('limit', 50);
        $limit = min(max($limit, 1), 200);

        $cursor = $request->input('cursor');
        $status = $request->input('status');
        $externalOrderId = $request->input('external_order_id');

        $query = Order::query()
            ->where('provider', $clientName)
            ->select([
                'id',
                'provider_order_id',
                'link',
                'quantity',
                'remains',
                'delivered',
                'status',
                'created_at',
                'updated_at',
                'provider_payload',
                'provider_last_error',
            ])
            ->orderByDesc('id');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }
        if ($externalOrderId !== null && $externalOrderId !== '') {
            $query->where('provider_order_id', $externalOrderId);
        }
        if ($cursor !== null && $cursor !== '') {
            $query->where('id', '<', (int) $cursor);
        }

        $orders = $query->limit($limit + 1)->get();
        $hasMore = $orders->count() > $limit;
        if ($hasMore) {
            $orders = $orders->take($limit);
        }
        $nextCursor = $hasMore ? $orders->last()?->id : null;

        $list = $orders->map(fn (Order $o) => $this->presentOrder($o));

        return response()->json([
            'ok' => true,
            'orders' => $list,
            'next_cursor' => $nextCursor,
        ]);
    }

    /**
     * Show a single order by external_order_id.
     */
    public function show(Request $request, string $external_order_id): JsonResponse
    {
        $clientName = (string) $request->attributes->get('external_client');

        $order = Order::query()
            ->where('provider', $clientName)
            ->where('provider_order_id', $external_order_id)
            ->first();

        if (!$order) {
            return response()->json([
                'ok' => false,
                'error' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'order' => $this->presentOrder($order),
        ]);
    }

    /**
     * Present order for external API: subscription progress only, no internal execution_phase.
     */
    private function presentOrder(Order $order): array
    {
        $quantity = (int) $order->quantity;
        $remains = (int) $order->remains;
        $delivered = (int) $order->delivered;

        $total = $quantity;
        $done = $delivered > 0 ? $delivered : (int) max(0, $quantity - $remains);
        $percent = $total > 0 ? round($done / $total * 100, 2) : 0.0;
        $isDone = $remains <= 0 || $order->status === Order::STATUS_COMPLETED;

        $status = $this->mapStatusForExternal($order, $remains, $isDone);

        $out = [
            'order_id' => $order->id,
            'external_order_id' => $order->provider_order_id,
            'link' => $order->link,
            'status' => $status,
            'quantity' => $quantity,
            'delivered' => $delivered,
            'remains' => $remains,
            'progress' => [
                'total' => $total,
                'done' => $done,
                'remains' => $remains,
                'percent' => $percent,
                'is_done' => $isDone,
            ],
            'timestamps' => [
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ],
        ];

        $errorMessage = $this->extractOrderErrorMessage($order);
        if ($errorMessage !== null && $errorMessage !== '') {
            $out['error'] = $errorMessage;
        }

        return $out;
    }

    /**
     * Map internal status to external; never expose unsubscribing.
     */
    private function mapStatusForExternal(Order $order, int $remains, bool $isDone): string
    {
        if ($remains <= 0 || $isDone) {
            return 'completed';
        }
        $status = $order->status;
        if ($status === Order::STATUS_COMPLETED) {
            return 'completed';
        }
        if ($status === Order::STATUS_CANCELED) {
            return 'canceled';
        }
        if (in_array($status, [
            Order::STATUS_INVALID_LINK,
            Order::STATUS_RESTRICTED,
            Order::STATUS_FAIL,
        ], true)) {
            return 'failed';
        }
        if ($status === Order::STATUS_VALIDATING) {
            return 'validating';
        }
        if (in_array($status, [
            Order::STATUS_AWAITING,
            Order::STATUS_PENDING,
            Order::STATUS_PENDING_DEPENDENCY,
        ], true)) {
            return 'awaiting';
        }
        if (in_array($status, [
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_PROCESSING,
            Order::STATUS_PARTIAL,
        ], true)) {
            return 'in_progress';
        }
        if ($status === null || $status === '') {
            return $order->delivered > 0 ? 'in_progress' : 'awaiting';
        }
        return 'in_progress';
    }

    private function extractOrderErrorMessage(Order $order): ?string
    {
        $err = $order->provider_last_error ?? null;
        if ($err !== null && (string) $err !== '') {
            return (string) $err;
        }
        $payload = $order->provider_payload;
        if (!is_array($payload)) {
            return null;
        }
        foreach (['error', 'last_error'] as $key) {
            if (!empty($payload[$key]) && is_string($payload[$key])) {
                return $payload[$key];
            }
        }
        return null;
    }

    private function resolveService(?string $serviceKey, ?int $serviceId): ?Service
    {
        if ($serviceKey !== null && $serviceKey !== '') {
            return Service::query()
                ->where('template_key', $serviceKey)
                ->where('is_active', true)
                ->first();
        }
        if ($serviceId !== null && $serviceId > 0) {
            return Service::query()
                ->where('id', $serviceId)
                ->where('is_active', true)
                ->first();
        }
        return null;
    }
}
