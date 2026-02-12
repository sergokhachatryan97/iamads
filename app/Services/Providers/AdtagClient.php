<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;

/**
 * HTTP client for Adtag provider API.
 * Used to fetch the provider service list for syncing into the local services table.
 */
class AdtagClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('providers.adtag.base_url', 'https://adtag.pro/api/v2'), '/');
        $this->apiKey = $apiKey ?? (string) config('providers.adtag.api_key');
        $this->timeout = $timeout;
    }

    /**
     * Fetch services list from Adtag API.
     *
     * GET {base_url}?action=services&key={api_key}
     *
     * @return array<int, array<string, mixed>> Array of service objects
     * @throws \RuntimeException If request fails or response is not valid JSON array
     */
    public function fetchServices(): array
    {
        $url = $this->baseUrl . '?' . http_build_query([
            'action' => 'services',
            'key' => $this->apiKey,
        ]);

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Adtag API request failed: HTTP ' . $response->status() . '. Response: ' . substr($response->body(), 0, 500)
            );
        }

        $body = $response->json();

        if (!is_array($body)) {
            throw new \RuntimeException(
                'Adtag API returned non-array body. Response: ' . substr($response->body(), 0, 500)
            );
        }

        return $body;
    }
}
