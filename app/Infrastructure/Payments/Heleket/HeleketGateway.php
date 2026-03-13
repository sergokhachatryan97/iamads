<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Heleket;

use App\Domain\Payments\DTO\GatewayWebhookEvent;
use App\Domain\Payments\DTO\PaymentInitiationResult;
use App\Domain\Payments\DTO\PaymentIntent;
use App\Domain\Payments\PaymentGatewayInterface;
use App\Domain\Payments\PaymentStatus;
use InvalidArgumentException;
use RuntimeException;


final class HeleketGateway implements PaymentGatewayInterface
{
    public function __construct(
        private HeleketClient $client,
        private string $webhookIp,
        private bool $enforceWebhookIp = true,
    ) {}

    public function initiate(PaymentIntent $intent): PaymentInitiationResult
    {
        $amount = number_format( (float)$intent->amount, 2, '.', '');
        $payload = [
            'amount' => $amount,
            'currency' => $intent->currency,
            'order_id' => $intent->orderId,
            'url_success' => $intent->urlSuccess,
            'url_return' => $intent->urlReturn,
            'webhook_url' => $intent->webhookUrl,
            'url_callback' => $intent->webhookUrl,
        ];

        $response = $this->client->post('/v1/payment', $payload);

        $state = (int) ($response['state'] ?? -1);
        if ($state !== 0) {
            $message = $response['message'] ?? null;
            $errors = $response['errors'] ?? null;
            $err = $message ?: (is_array($errors) ? json_encode($errors) : 'Unknown error');
            throw new RuntimeException("Heleket payment create failed: {$err}");
        }

        $result = $response['result'] ?? null;

        if (!is_array($result)) {
            throw new RuntimeException('Heleket API: missing result');
        }

        $uuid = $result['uuid'] ?? null;
        $url = $result['url'] ?? null;
        if (!$uuid || !$url) {
            throw new RuntimeException('Heleket API: result missing uuid or url');
        }

        return new PaymentInitiationResult(
            provider: 'heleket',
            providerRef: (string) $uuid,
            payUrl: (string) $url,
            raw: $response,
        );
    }

    public function parseWebhook(string $rawBody, array $headers, string $ip): GatewayWebhookEvent
    {
        $this->validateIp($ip);

        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            throw new InvalidArgumentException('Invalid webhook JSON');
        }

        $sign = $body['sign'] ?? null;
        if ($sign === null || $sign === '') {
            throw new InvalidArgumentException('Missing webhook sign');
        }

        $bodyWithoutSign = $body;
        unset($bodyWithoutSign['sign']);
        ksort($bodyWithoutSign); // Ensure deterministic JSON for verification
        $expected = $this->client->computeSign($bodyWithoutSign);

        if (!hash_equals($expected, (string) $sign)) {
            throw new InvalidArgumentException('Invalid webhook signature');
        }

        $orderId = $body['order_id'] ?? null;
        $uuid = $body['uuid'] ?? $body['provider_ref'] ?? null;
        if (!$orderId || !$uuid) {
            throw new InvalidArgumentException('Missing order_id or uuid in webhook');
        }

        $status = $this->normalizeStatus($body);

        return new GatewayWebhookEvent(
            orderId: (string) $orderId,
            providerRef: (string) $uuid,
            status: $status,
            raw: $body,
        );
    }

    private function validateIp(string $ip): void
    {
        if (!$this->enforceWebhookIp) {
            return;
        }
        $allowed = array_filter(array_map('trim', explode(',', $this->webhookIp)));
        if ($allowed !== [] && !in_array($ip, $allowed, true)) {
            throw new InvalidArgumentException('Webhook IP not allowed');
        }
    }

    /**
     * Map Heleket statuses to normalized:
     * paid, paid_over => PAID
     * check => PENDING
     * wrong_amount => PENDING
     * cancel => EXPIRED
     * else => FAILED
     */
    private function normalizeStatus(array $body): PaymentStatus
    {
        $status = (string) ($body['payment_status'] ?? $body['status'] ?? '');
        $status = strtolower(trim($status));

        return match ($status) {
            'paid', 'paid_over' => PaymentStatus::PAID,
            'check' => PaymentStatus::PENDING,
            'wrong_amount' => PaymentStatus::PENDING,
            'cancel' => PaymentStatus::EXPIRED,
            default => PaymentStatus::FAILED,
        };
    }
}
