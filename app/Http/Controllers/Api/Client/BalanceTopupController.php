<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Application\Payments\InitiatePaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BalanceTopupRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class BalanceTopupController extends Controller
{
    public function __construct(
        private InitiatePaymentService $initiateService,
    ) {}

    /**
     * POST /api/clients/{client}/balance/topup
     * Initiate balance top-up. Returns pay_url for redirect.
     * Balance is credited ONLY on webhook PAID; redirects never credit.
     */
    public function topup(BalanceTopupRequest $request, Client $client): JsonResponse
    {
        $validated = $request->validated();

        $orderId = 'balance_' . $client->id . '_' . Str::ulid();

        try {
            $result = $this->initiateService->run(
                orderId: $orderId,
                amount: (string) $validated['amount'],
                currency: strtoupper($validated['currency']),
                provider: $validated['provider'],
                clientId: $client->id,
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
