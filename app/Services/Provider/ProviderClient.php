<?php

namespace App\Services\Provider;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProviderClient
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.provider.base_url', '');
        $this->apiKey = config('services.provider.api_key');
    }

    /**
     * Send order creation request to provider.
     *
     * @param Order $order
     * @return array{ok: bool, provider_order_id: string|null, start_count: int|null, status: string|null, raw: array|null, error: string|null}
     */
    public function createOrder(Order $order): array
    {
        $payload = [
            'service_id' => $order->service_id,
            'link' => $order->link,
            'quantity' => $order->quantity,
            'local_order_id' => $order->id,
        ];

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->post("{$this->baseUrl}/orders", $payload);

            $rawResponse = $response->json() ?? [];

            if ($response->successful()) {
                $providerOrderId = $this->extractProviderOrderId($rawResponse);
                $startCount = $this->extractValue($rawResponse, ['start_count', 'startCount', 'data.start_count']);
                $status = $this->extractValue($rawResponse, ['status', 'state', 'data.status']);

                return [
                    'ok' => true,
                    'provider_order_id' => $providerOrderId,
                    'start_count' => $startCount !== null ? (int) $startCount : null,
                    'status' => $status,
                    'raw' => $rawResponse,
                    'error' => null,
                ];
            }

            $errorMessage = $this->extractErrorMessage($rawResponse, $response->status());

            return [
                'ok' => false,
                'provider_order_id' => null,
                'start_count' => null,
                'status' => null,
                'raw' => $rawResponse,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('Provider API create order failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'provider_order_id' => null,
                'start_count' => null,
                'status' => null,
                'raw' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch order status from provider.
     *
     * @param string $providerOrderId
     * @return array{ok: bool, status: string|null, start_count: int|null, delivered: int|null, remains: int|null, raw: array|null, error: string|null}
     */
    public function fetchStatus(string $providerOrderId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->get("{$this->baseUrl}/orders/{$providerOrderId}");

            $rawResponse = $response->json() ?? [];

            if ($response->successful()) {
                $status = $this->extractValue($rawResponse, ['status', 'state', 'data.status']);
                $startCount = $this->extractValue($rawResponse, ['start_count', 'startCount', 'data.start_count']);
                $delivered = $this->extractValue($rawResponse, ['delivered', 'completed', 'data.delivered']);
                $remains = $this->extractValue($rawResponse, ['remains', 'rem', 'data.remains']);

                return [
                    'ok' => true,
                    'status' => $status,
                    'start_count' => $startCount !== null ? (int) $startCount : null,
                    'delivered' => $delivered !== null ? (int) $delivered : null,
                    'remains' => $remains !== null ? (int) $remains : null,
                    'raw' => $rawResponse,
                    'error' => null,
                ];
            }

            $errorMessage = $this->extractErrorMessage($rawResponse, $response->status());

            return [
                'ok' => false,
                'status' => null,
                'start_count' => null,
                'delivered' => null,
                'remains' => null,
                'raw' => $rawResponse,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('Provider API fetch status failed', [
                'provider_order_id' => $providerOrderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => null,
                'start_count' => null,
                'delivered' => null,
                'remains' => null,
                'raw' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get headers for API requests.
     */
    protected function getHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        return $headers;
    }

    /**
     * Extract provider order ID from response using common key patterns.
     */
    protected function extractProviderOrderId(array $data): ?string
    {
        // Try common keys: order_id, order, id, data.order_id, data.id
        $keys = ['order_id', 'order', 'id', 'data.order_id', 'data.id'];

        foreach ($keys as $key) {
            $value = $this->getNestedValue($data, $key);
            if ($value !== null) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Extract a value from response using multiple possible keys.
     *
     * @param array $data
     * @param array<string> $possibleKeys
     * @return mixed
     */
    protected function extractValue(array $data, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            $value = $this->getNestedValue($data, $key);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get nested value from array using dot notation.
     *
     * @param array $data
     * @param string $key
     * @return mixed
     */
    protected function getNestedValue(array $data, string $key)
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $value = $data;

            foreach ($keys as $k) {
                if (!isset($value[$k])) {
                    return null;
                }
                $value = $value[$k];
            }

            return $value;
        }

        return $data[$key] ?? null;
    }

    /**
     * Extract error message from response.
     */
    protected function extractErrorMessage(array $rawResponse, int $statusCode): string
    {
        $errorKeys = ['message', 'error', 'error_message', 'data.message', 'data.error'];

        foreach ($errorKeys as $key) {
            $error = $this->getNestedValue($rawResponse, $key);
            if ($error) {
                return (string) $error;
            }
        }

        return "HTTP {$statusCode}";
    }
}

