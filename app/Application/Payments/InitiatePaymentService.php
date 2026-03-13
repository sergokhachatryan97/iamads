<?php

declare(strict_types=1);

namespace App\Application\Payments;

use App\Domain\Payments\DTO\PaymentIntent;
use App\Domain\Payments\DTO\PaymentInitiationResult;
use App\Domain\Payments\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Initiates a payment via the chosen gateway and persists the result.
 */
final class InitiatePaymentService
{
    public function __construct(
        private PaymentGatewayResolver $resolver,
    ) {}

    /**
     * @param string $orderId Internal order identifier
     * @param string $amount Decimal string (e.g. "10.50")
     * @param string $currency ISO 4217 (e.g. "USD")
     * @param string $provider Gateway key (e.g. "heleket")
     * @param int|null $clientId Client ID for balance top-up (required for crediting on webhook)
     * @return array{pay_url: string, provider_ref: string, status: string}
     */
    public function run(string $orderId, string $amount, string $currency, string $provider, ?int $clientId = null): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $urlSuccess = "{$baseUrl}/balance/add?status=success&provider={$provider}&order_id=" . urlencode($orderId);
        $urlReturn = "{$baseUrl}/balance/add?status=return&provider={$provider}&order_id=" . urlencode($orderId);
        $webhookUrl = "{$baseUrl}/api/webhooks/payments/{$provider}";

        $intent = new PaymentIntent(
            orderId: $orderId,
            amount: $amount,
            currency: $currency,
            provider: $provider,
            urlSuccess: $urlSuccess,
            urlReturn: $urlReturn,
            webhookUrl: $webhookUrl,
        );

        $gateway = $this->resolver->resolve($provider);

        $result = $gateway->initiate($intent);

        return DB::transaction(function () use ($orderId, $provider, $amount, $currency, $result, $clientId) {
            $payment = Payment::create([
                'client_id' => $clientId,
                'order_id' => $orderId,
                'provider' => $provider,
                'provider_ref' => $result->providerRef,
                'amount' => $amount,
                'currency' => $currency,
                'status' => PaymentStatus::PENDING->value,
                'pay_url' => $result->payUrl,
                'meta' => $result->raw,
            ]);

            return [
                'pay_url' => $result->payUrl,
                'provider_ref' => $result->providerRef,
                'status' => $payment->status,
            ];
        });
    }
}
