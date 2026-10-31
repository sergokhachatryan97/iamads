<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Baosbabs;

use Illuminate\Support\Facades\Http;

/**
 * Baosbabs (Tron Payment Gateway) API client.
 * Auth: X-API-Key header. Success: {"success": true, "data": {...}};
 * error: {"error": "CODE", "message": "..."}.
 */
final class BaosbabsClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function get(string $path): array
    {
        return $this->send('get', $path);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->send('post', $path, $body);
    }

    /**
     * Returns the "data" object of a successful response.
     */
    private function send(string $method, string $path, array $body = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $request = Http::connectTimeout(5)->timeout(15)->withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ]);

        $response = $method === 'post' ? $request->post($url, $body) : $request->get($url);

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new \RuntimeException("Baosbabs API returned invalid JSON (HTTP {$response->status()})");
        }

        if (($decoded['success'] ?? false) !== true) {
            $code = $decoded['error'] ?? 'UNKNOWN';
            $message = $decoded['message'] ?? 'Unknown error';
            throw new \RuntimeException("Baosbabs API error {$code}: {$message}");
        }

        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            throw new \RuntimeException('Baosbabs API: missing data');
        }

        return $data;
    }
}
