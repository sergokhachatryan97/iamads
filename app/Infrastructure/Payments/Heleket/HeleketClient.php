<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Heleket;

use Illuminate\Support\Facades\Http;

/**
 * Heleket API client.
 * All requests are POST JSON. Auth: merchant + sign.
 * sign = md5(base64_encode(json_encode(body)) . API_KEY)
 * If body empty: sign = md5(base64_encode('') . API_KEY)
 */
final class HeleketClient
{
    public function __construct(
        private string $baseUrl,
        private string $merchant,
        private string $paymentKey,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * POST JSON to endpoint. Sign computed from request body.
     */
    public function post(string $path, array $body = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $jsonBody = $body === [] ? '' : json_encode($body);
        $encoded = base64_encode($jsonBody);
        $sign = md5($encoded . $this->paymentKey);

        $response = Http::withHeaders([
            'merchant' => $this->merchant,
            'sign' => $sign,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->withBody($jsonBody, 'application/json')->post($url);

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new \RuntimeException('Heleket API returned invalid JSON');
        }

        return $decoded;
    }

    /**
     * Compute sign for verification: md5(base64_encode(json_encode(body_without_sign)) . API_KEY)
     */
    public function computeSign(array $bodyWithoutSign): string
    {
        $jsonBody = json_encode($bodyWithoutSign);
        $encoded = base64_encode($jsonBody);
        return md5($encoded . $this->paymentKey);
    }
}
