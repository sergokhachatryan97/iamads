<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Heleket;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Heleket Test Webhook API client.
 * Endpoint: POST /v1/test-webhook/payment
 * sign = md5(base64_encode(json_encode(body)) . PAYMENT_API_KEY)
 */
final class HeleketTestWebhookClient
{
    public function __construct(
        private string $baseUrl,
        private string $paymentKey,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Send test payment webhook. Throws if response.state != 0.
     */
    public function sendTestPaymentWebhook(array $payload): array
    {
        $url = $this->baseUrl . '/v1/test-webhook/payment';

        $jsonBody = json_encode($payload);
        $encoded = base64_encode($jsonBody);
        $sign = md5($encoded . $this->paymentKey);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'sign' => $sign,
        ])->withBody($jsonBody, 'application/json')->post($url);

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new RuntimeException('Heleket test-webhook API returned invalid JSON');
        }

        $state = (int) ($decoded['state'] ?? -1);
        if ($state !== 0) {
            $message = $decoded['message'] ?? 'Unknown error';
            throw new RuntimeException("Heleket test-webhook failed: {$message}");
        }

        return $decoded;
    }
}
