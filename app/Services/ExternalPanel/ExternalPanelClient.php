<?php

namespace App\Services\ExternalPanel;

use App\Models\ExternalProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Generic HTTP client for standard SMM v2 panel API (Perfect Panel, SocPanel, etc.).
 *
 * All v2 panels share the same POST contract:
 *   POST {base_url}  with  key, action, and action-specific fields.
 */
class ExternalPanelClient
{
    private string $baseUrl;

    private string $apiKey;

    private int $timeout;

    public function __construct(string $baseUrl, string $apiKey, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    /**
     * Build a client from a provider code (looks up in external_providers table).
     */
    public static function forProvider(string $providerCode): self
    {
        $provider = ExternalProvider::where('code', $providerCode)->first();

        if (! $provider) {
            throw new RuntimeException("External provider not found: {$providerCode}");
        }

        if (! $provider->is_active) {
            throw new RuntimeException("External provider is disabled: {$providerCode}");
        }

        return new self(
            $provider->base_url,
            $provider->api_key,
            $provider->timeout,
        );
    }

    /**
     * Create an order on the external panel.
     *
     * @return array{order: int|string} Remote order ID on success.
     *
     * @throws RuntimeException
     */
    public function addOrder(int|string $remoteServiceId, string $link, int $quantity): array
    {
        $response = $this->post([
            'action' => 'add',
            'service' => $remoteServiceId,
            'link' => $link,
            'quantity' => $quantity,
        ]);

        if (isset($response['error'])) {
            throw new RuntimeException("External panel add error: {$response['error']}");
        }

        if (! isset($response['order'])) {
            throw new RuntimeException('External panel add: missing order ID in response');
        }

        return $response;
    }

    /**
     * Fetch order status from the external panel.
     *
     * @return array{status: string, charge: string, start_count: string, remains: string, currency: string}
     *
     * @throws RuntimeException
     */
    public function orderStatus(int|string $remoteOrderId): array
    {
        $response = $this->post([
            'action' => 'status',
            'order' => $remoteOrderId,
        ]);

        if (isset($response['error'])) {
            throw new RuntimeException("External panel status error: {$response['error']}");
        }

        return $response;
    }

    /**
     * Fetch available services from the external panel.
     */
    public function services(): array
    {
        return $this->post(['action' => 'services']);
    }

    /**
     * Fetch balance from the external panel.
     */
    public function balance(): array
    {
        return $this->post(['action' => 'balance']);
    }

    /**
     * Send POST request to the v2 API.
     */
    private function post(array $payload): array
    {
        $payload['key'] = $this->apiKey;

        $response = Http::connectTimeout(10)
            ->timeout($this->timeout)
            ->asForm()
            ->post($this->baseUrl, $payload);

        if ($response->failed()) {
            Log::error('External panel HTTP error', [
                'url' => $this->baseUrl,
                'action' => $payload['action'] ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("External panel HTTP error: {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('External panel returned non-JSON response');
        }

        return $data;
    }
}
