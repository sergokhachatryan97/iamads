<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Baosbabs;

use App\Domain\Payments\DTO\GatewayWebhookEvent;
use App\Domain\Payments\DTO\PaymentInitiationResult;
use App\Domain\Payments\DTO\PaymentIntent;
use App\Domain\Payments\PaymentGatewayInterface;
use App\Domain\Payments\PaymentStatus;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use RuntimeException;

/**
 * One gateway serves several provider keys (baosbabs_trx, baosbabs_usdt_trc20,
 * baosbabs_usdt_bep20); the asset is picked from the provider key so each coin
 * shows up as its own payment method without changes to the payment flow.
 *
 * Baosbabs has no hosted checkout: create returns a wallet address and crypto
 * amount, so payUrl points to our own signed page that displays them.
 */
final class BaosbabsGateway implements PaymentGatewayInterface
{
    /**
     * @param  array<string, string>  $assets  provider key => Baosbabs asset code
     */
    public function __construct(
        private BaosbabsClient $client,
        private array $assets,
        private string $webhookIp = '',
    ) {}

    public function initiate(PaymentIntent $intent): PaymentInitiationResult
    {
        $asset = $this->assets[$intent->provider] ?? null;
        if (!$asset) {
            throw new RuntimeException("Baosbabs: no asset configured for provider {$intent->provider}");
        }

        if (strtoupper($intent->currency) !== 'USD') {
            throw new RuntimeException('Baosbabs supports USD amounts only');
        }

        $data = $this->client->post('/api/payment/create', [
            'transaction_id' => $intent->orderId,
            'asset' => $asset,
            'amount_usd' => number_format((float) $intent->amount, 2, '.', ''),
            'postback_url' => $intent->webhookUrl,
        ]);

        if (empty($data['wallet_address']) || !isset($data['amount_crypto'])) {
            throw new RuntimeException('Baosbabs API: response missing wallet_address or amount_crypto');
        }

        $payUrl = URL::signedRoute('payments.crypto.show', ['orderId' => $intent->orderId]);

        return new PaymentInitiationResult(
            provider: $intent->provider,
            providerRef: (string) ($data['transaction_id'] ?? $intent->orderId),
            payUrl: $payUrl,
            // The pay page reads the invoice and redirect targets from payment meta.
            raw: [
                'invoice' => $data,
                'url_success' => $intent->urlSuccess,
                'url_return' => $intent->urlReturn,
            ],
        );
    }

    public function parseWebhook(string $rawBody, array $headers, string $ip): GatewayWebhookEvent
    {
        $this->validateIp($ip);

        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            parse_str($rawBody, $body);
        }

        $transactionId = $body['transaction_id'] ?? null;
        if (!is_string($transactionId) || $transactionId === '') {
            throw new InvalidArgumentException('Missing transaction_id in webhook');
        }

        // Postbacks are unsigned, so the body is never trusted: re-read the
        // payment from the API with our key and use that status instead.
        try {
            $verified = $this->client->get('/api/payment/' . rawurlencode($transactionId) . '/status');
        } catch (RuntimeException $e) {
            throw new InvalidArgumentException('Webhook verification failed: ' . $e->getMessage());
        }

        if ((string) ($verified['transaction_id'] ?? '') !== $transactionId) {
            throw new InvalidArgumentException('Webhook verification returned a different transaction');
        }

        return new GatewayWebhookEvent(
            orderId: $transactionId,
            providerRef: $transactionId,
            status: $this->normalizeStatus((string) ($verified['status'] ?? '')),
            raw: [
                'postback' => $body,
                'verified' => $verified,
            ],
        );
    }

    private function validateIp(string $ip): void
    {
        $allowed = array_filter(array_map('trim', explode(',', $this->webhookIp)));
        if ($allowed !== [] && !in_array($ip, $allowed, true)) {
            throw new InvalidArgumentException('Webhook IP not allowed');
        }
    }

    /**
     * completed => PAID, expired => EXPIRED, failed => FAILED, anything else => PENDING.
     */
    private function normalizeStatus(string $status): PaymentStatus
    {
        return match (strtolower(trim($status))) {
            'completed' => PaymentStatus::PAID,
            'expired' => PaymentStatus::EXPIRED,
            'failed' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
