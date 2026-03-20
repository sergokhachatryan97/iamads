<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\External\ExternalOrderStoreRequest;
use App\Models\Order;
use App\Services\OrderServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * External Orders API – client-account-based.
 * Status glossary: validating | awaiting | in_progress | completed | canceled | failed
 */
class ExternalOrderController extends Controller
{
    public function __construct(
        private OrderServiceInterface $orderService
    ) {}

    /**
     * Create a single order (idempotent by client_id + external_order_id).
     */
    public function store(ExternalOrderStoreRequest $request): JsonResponse
    {
        $client = $request->attributes->get('api_client');

        try {
            $result = $this->orderService->createApiOrder($client, [
                'external_order_id' => $request->validated('external_order_id'),
                'service_id' => (int) $request->validated('service'),
                'link' => $request->validated('link'),
                'quantity' => (int) $request->validated('quantity'),
                'speed_tier' => $request->validated('speed_tier'),
                'meta' => $request->validated('meta') ?? [],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'error' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'order' => $this->presentOrder($result->order),
            'duplicate' => $result->duplicate,
        ], $result->duplicate ? 200 : 201);
    }

    /**
     * List orders for the authenticated API client.
     */
    public function index(Request $request): JsonResponse
    {
        $client = $request->attributes->get('api_client');

        $limit = min(max((int) $request->input('limit', 50), 1), 200);
        $cursor = $request->input('cursor');
        $status = $request->input('status');
        $externalOrderId = $request->input('external_order_id');

        $query = Order::query()
            ->where('client_id', $client->id)
            ->select([
                'id',
                'source',
                'external_order_id',
                'provider_order_id',
                'link',
                'quantity',
                'remains',
                'delivered',
                'status',
                'charge',
                'created_at',
                'updated_at',
                'provider_payload',
                'provider_last_error',
            ])
            ->orderByDesc('id');

        if ($status !== null && $status !== '') {
            $query->whereIn('status', $this->internalStatusesForPublic($status));
        }
        if ($externalOrderId !== null && $externalOrderId !== '') {
            $query->where('external_order_id', $externalOrderId);
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

        return response()->json([
            'ok' => true,
            'orders' => $orders->map(fn (Order $o) => $this->presentOrder($o)),
            'next_cursor' => $nextCursor,
        ]);
    }

    /**
     * Show one order by external_order_id.
     */
    public function show(Request $request, string $external_order_id): JsonResponse
    {
        $client = $request->attributes->get('api_client');

        $order = Order::query()
            ->where('client_id', $client->id)
            ->where(function ($q) use ($external_order_id) {
                $q->where('external_order_id', $external_order_id)
                    ->orWhere('provider_order_id', $external_order_id);
            })
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
     * POST /external/orders/statuses – batch status check.
     */
    public function statuses(Request $request): JsonResponse
    {
        $request->validate([
            'orders' => ['required', 'array'],
            'orders.*' => ['string', 'max:255'],
        ]);

        $client = $request->attributes->get('api_client');
        $externalIds = array_values(array_unique($request->input('orders', [])));

        if (empty($externalIds)) {
            return response()->json(['ok' => true, 'orders' => []]);
        }

        $orders = Order::query()
            ->where('client_id', $client->id)
            ->where(function ($q) use ($externalIds) {
                $q->whereIn('external_order_id', $externalIds)
                    ->orWhereIn('provider_order_id', $externalIds);
            })
            ->get();

        $byExtId = [];
        foreach ($orders as $order) {
            $extId = $order->external_order_id ?? $order->provider_order_id;
            if ($extId !== null) {
                $byExtId[$extId] = $order;
            }
        }

        $result = [];
        foreach ($externalIds as $extId) {
            $order = $byExtId[$extId] ?? null;
            $result[$extId] = $order ? $this->presentOrder($order) : null;
        }

        return response()->json([
            'ok' => true,
            'orders' => $result,
        ]);
    }

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
            'external_order_id' => $order->external_order_id ?? $order->provider_order_id,
            'link' => $order->link,
            'status' => $status,
            'quantity' => $quantity,
            'delivered' => $delivered,
            'remains' => $remains,
            'charge' => (float) $order->charge,
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
        return $order->delivered > 0 ? 'in_progress' : 'awaiting';
    }

    /**
     * Map public status to internal statuses for filtering.
     */
    private function internalStatusesForPublic(string $publicStatus): array
    {
        return match ($publicStatus) {
            'validating' => [Order::STATUS_VALIDATING],
            'awaiting' => [Order::STATUS_AWAITING, Order::STATUS_PENDING, Order::STATUS_PENDING_DEPENDENCY],
            'in_progress' => [Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING, Order::STATUS_PARTIAL],
            'completed' => [Order::STATUS_COMPLETED],
            'canceled' => [Order::STATUS_CANCELED],
            'failed' => [Order::STATUS_INVALID_LINK, Order::STATUS_RESTRICTED, Order::STATUS_FAIL],
            default => [],
        };
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
}
