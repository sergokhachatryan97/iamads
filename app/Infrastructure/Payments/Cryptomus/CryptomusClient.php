<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Cryptomus;

use Illuminate\Support\Facades\Http;

/**
 * Cryptomus API client.
 * Auth: merchant + sign headers.
 * sign = md5(base64_encode(json_encode(body)) . PAYMENT_KEY)
 * If body empty: sign = md5(base64_encode('') . PAYMENT_KEY)
 */
final class CryptomusClient
{
    public function __construct(
        private string $baseUrl,
        private string $merchant,
        private string $paymentKey,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function post(string $path, array $body = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $jsonBody = $body === [] ? '' : json_encode($body);
        $encoded  = base64_encode($jsonBody);
        $sign     = md5($encoded . $this->paymentKey);

        $response = Http::connectTimeout(5)->timeout(15)->withHeaders([
            'merchant'     => $this->merchant,
            'sign'         => $sign,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ])->withBody($jsonBody, 'application/json')->post($url);

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new \RuntimeException('Cryptomus API returned invalid JSON');
        }

        return $decoded;
    }

    public function computeSign(array $bodyWithoutSign): string
    {
        $jsonBody = json_encode($bodyWithoutSign);
        $encoded  = base64_encode($jsonBody);
        return md5($encoded . $this->paymentKey);
    }
}
