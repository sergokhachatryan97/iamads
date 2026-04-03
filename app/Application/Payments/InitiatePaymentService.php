<?php

declare(strict_types=1);

namespace App\Application\Payments;

use App\Domain\Payments\DTO\PaymentIntent;
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
     * @param  string  $orderId  Internal order identifier
     * @param  string  $amount  Decimal string (e.g. "10.50")
     * @param  string  $currency  ISO 4217 (e.g. "USD")
     * @param  string  $provider  Gateway key (e.g. "heleket")
     * @param  int|null  $clientId  Client ID for balance top-up (required for crediting on webhook)
     * @param  string|null  $urlSuccess  Override success redirect URL (e.g. guest fast order → home)
     * @param  string|null  $urlReturn  Override return URL
     * @param  array<string, mixed>  $metaMerge  Merged into stored payment meta (e.g. fast_order_uuid for webhooks)
     * @return array{pay_url: string, provider_ref: string, status: string}
     */
    public function run(
        string $orderId,
        string $amount,
        string $currency,
        string $provider,
        ?int $clientId = null,
        ?string $urlSuccess = null,
        ?string $urlReturn = null,
        array $metaMerge = [],
    ): array {
        $baseUrl = rtrim(config('app.url'), '/');
        $urlSuccess ??= "{$baseUrl}/balance/add?status=success&provider={$provider}&order_id=".urlencode($orderId);
        $urlReturn ??= "{$baseUrl}/balance/add?status=return&provider={$provider}&order_id=".urlencode($orderId);
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

        return DB::transaction(function () use ($orderId, $provider, $amount, $currency, $result, $clientId, $metaMerge) {
            $meta = array_merge(is_array($result->raw) ? $result->raw : [], $metaMerge);
            $payment = Payment::create([
                'client_id' => $clientId,
                'order_id' => $orderId,
                'provider' => $provider,
                'provider_ref' => $result->providerRef,
                'amount' => $amount,
                'currency' => $currency,
                'status' => PaymentStatus::PENDING->value,
                'pay_url' => $result->payUrl,
                'meta' => $meta,
            ]);

            return [
                'pay_url' => $result->payUrl,
                'provider_ref' => $result->providerRef,
                'status' => $payment->status,
            ];
        });
    }
}
