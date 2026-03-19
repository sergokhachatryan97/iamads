<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFastOrderRequest;
use App\Models\FastOrder;
use App\Services\FastOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FastOrderController extends Controller
{
    public function __construct(
        private FastOrderService $fastOrderService
    ) {}

    /**
     * POST /api/fast-orders — Create fast order draft.
     */
    public function store(StoreFastOrderRequest $request): JsonResponse
    {
        $payload = $request->payload();
        $fastOrder = $this->fastOrderService->createDraft($payload);

        return response()->json([
            'message' => 'Fast order draft created.',
            'fast_order' => [
                'uuid' => $fastOrder->uuid,
                'status' => $fastOrder->status,
                'payment_status' => $fastOrder->payment_status,
                'total_amount' => $fastOrder->total_amount,
                'currency' => $fastOrder->currency,
            ],
        ], 201);
    }

    /**
     * GET /api/fast-orders/{uuid} — Show fast order (for debugging/testing).
     */
    public function show(string $uuid): JsonResponse
    {
        $fastOrder = FastOrder::where('uuid', $uuid)->firstOrFail();
        $fastOrder->load(['category', 'service', 'client', 'order']);

        return response()->json([
            'fast_order' => $fastOrder,
        ]);
    }

    /**
     * POST /api/fast-orders/{uuid}/payment-method — Set payment method.
     */
    public function setPaymentMethod(string $uuid, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:64'],
        ]);

        $fastOrder = FastOrder::where('uuid', $uuid)->firstOrFail();
        $fastOrder = $this->fastOrderService->setPaymentMethod($fastOrder, $validated['payment_method']);

        return response()->json([
            'message' => 'Payment method set.',
            'fast_order' => [
                'uuid' => $fastOrder->uuid,
                'status' => $fastOrder->status,
                'payment_method' => $fastOrder->payment_method,
            ],
        ]);
    }

    /**
     * POST /api/fast-orders/{uuid}/simulate-payment-success — Simulate payment success and convert to real order.
     */
    public function simulatePaymentSuccess(string $uuid): JsonResponse
    {
        $fastOrder = FastOrder::where('uuid', $uuid)->firstOrFail();
        $result = $this->fastOrderService->markAsPaidAndConvert($fastOrder);

        return response()->json([
            'message' => 'Payment simulated; account and order created.',
            ...$result,
        ]);
    }
}
