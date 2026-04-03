<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Payments\InitiatePaymentService;
use App\Application\Payments\PaymentGatewayResolver;
use App\Http\Controllers\Controller;
use App\Models\FastOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private InitiatePaymentService $initiateService,
        private PaymentGatewayResolver $resolver,
    ) {}

    /**
     * POST /api/payments/{provider}/initiate
     * Body (balance top-up): { order_id, amount, currency, client_id? }
     * Body (guest fast order): { fast_order_uuid } — amount/currency taken from the draft.
     */
    public function initiate(string $provider, Request $request): JsonResponse
    {
        $isFastOrder = $request->filled('fast_order_uuid');

        if ($isFastOrder) {
            $validated = $request->validate([
                'fast_order_uuid' => ['required', 'uuid', 'exists:fast_orders,uuid'],
            ]);
        } else {
            $validated = $request->validate([
                'order_id' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,8})?$/'],
                'currency' => ['required', 'string', 'size:3'],
                'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            ]);
        }

        if (! in_array($provider, $this->resolver->supported(), true)) {
            return response()->json(['message' => 'Unknown provider'], 404);
        }

        try {
            if ($isFastOrder) {
                $fastOrder = FastOrder::query()->where('uuid', $validated['fast_order_uuid'])->firstOrFail();
                if (! in_array($fastOrder->status, [FastOrder::STATUS_DRAFT, FastOrder::STATUS_PENDING_PAYMENT], true)) {
                    return response()->json([
                        'message' => __('This checkout cannot be paid anymore.'),
                    ], 422);
                }
                $amountNum = (float) $fastOrder->total_amount;
                if ($amountNum <= 0) {
                    return response()->json(['message' => __('Invalid order total.')], 422);
                }
                $amount = number_format($amountNum, 2, '.', '');
                $currency = strtoupper((string) $fastOrder->currency);
                $orderId = 'fast_'.$fastOrder->id.'_'.bin2hex(random_bytes(4));
                $uuid = $fastOrder->uuid;
                $urlSuccess = route('fast-order.after-payment', ['uuid' => $uuid], absolute: true);
                $urlReturn = route('fast-order.after-payment', ['uuid' => $uuid], absolute: true);

                $result = $this->initiateService->run(
                    $orderId,
                    $amount,
                    $currency,
                    $provider,
                    null,
                    $urlSuccess,
                    $urlReturn,
                    ['fast_order_uuid' => $uuid],
                );

                return response()->json($result);
            }

            $result = $this->initiateService->run(
                $validated['order_id'],
                $validated['amount'],
                strtoupper($validated['currency']),
                $provider,
                $validated['client_id'] ?? null,
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
