<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Payments\InitiatePaymentService;
use App\Application\Payments\PaymentGatewayResolver;
use App\Http\Controllers\Controller;
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
     * Body: { order_id, amount, currency }
     */
    public function initiate(string $provider, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,8})?$/'],
            'currency' => ['required', 'string', 'size:3'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        if (!in_array($provider, $this->resolver->supported(), true)) {
            return response()->json(['message' => 'Unknown provider'], 404);
        }

        try {
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
