<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

/**
 * HTTP client for Socpanel private API.
 * Used to poll getOrders and ingest active orders into the local orders table.
 */
class SocpanelClient
{
    private string $baseUrl;
    private string $token;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $token = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('providers.socpanel.base_url', 'https://socpanel.com/privateApi'), '/');
        $this->token = $token ?? (string) config('providers.socpanel.token');
        $this->timeout = $timeout ?? (int) config('providers.socpanel.timeout', 60);
    }

    public function getOrders(
        int $providerServiceId,
        string $status,
        int $limit = 100,
        int $offset = 0
    ): array {
        $url = rtrim($this->baseUrl, '/') . '/getOrders';

        $query = [
            'service_id' => $providerServiceId,
            'status'     => $status,
            'limit'      => $limit,
            'offset'     => $offset,
            'token'      => $this->token,
        ];

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->get($url, $query);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Socpanel getOrders failed: ' . $response->body()
            );
        }

        $body = $response->json();

        if (!is_array($body)) {
            throw new \RuntimeException('Socpanel getOrders returned invalid JSON');
        }

        return $body;
    }


    /**
     * Fetch one page of completed orders. API returns { count, items } and uses offset/limit pagination.
     *
     * @param int $offset Pagination offset (0-based)
     * @param int $limit Page size (1–100)
     * @param string|null $dateFrom Optional Y-m-d
     * @param string|null $dateTo Optional Y-m-d
     * @return array{items: array, count: int, has_more: bool}
     */
    public function getCompletedOrdersPage(int $offset = 0, int $limit = 100, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return $this->getOrdersByStatusPage('completed', $offset, $limit, $dateFrom, $dateTo);
    }

    /**
     * Fetch one page of orders by status. API returns { count, items } with offset/limit pagination.
     *
     * @param string $status e.g. 'completed', 'partial', 'active'
     * @param int $offset Pagination offset (0-based)
     * @param int $limit Page size (1–100)
     * @param string|null $dateFrom Optional Y-m-d
     * @param string|null $dateTo Optional Y-m-d
     * @return array{items: array, count: int, has_more: bool}
     */
    public function getOrdersByStatusPage(string $status, int $offset = 0, int $limit = 100, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $url = $this->baseUrl . '/getOrders';

        $payload = [
            'token'  => $this->token,
            'status' => $status,
            'limit'  => max(1, min($limit, 100)),
            'offset' => max(0, $offset),
        ];

        if ($dateFrom !== null && $dateFrom !== '') {
            $payload['date_from'] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $payload['date_to'] = $dateTo;
        }

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if (!$response->successful()) {
            $this->throwRequestException($response, $url);
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new \RuntimeException('Socpanel getOrders returned non-array body');
        }

        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        $totalCount = (int) ($body['count'] ?? 0);
        $hasMore = $totalCount > 0 && ($offset + count($items)) < $totalCount;

        return [
            'items'    => $items,
            'count'    => $totalCount,
            'has_more' => $hasMore,
            'raw'      => $body,
        ];
    }


    public function editOrder(
        int $orderId,
        ?string $status = null,
        ?int $startCount = null,
        ?int $completions = null
    ): array {
        $url = rtrim($this->baseUrl, '/') . '/editOrder';

        $query = [
            'order_id' => $orderId,
            'token'    => $this->token,
        ];

        if ($status !== null) {
            $query['status'] = $status; // active | completed | canceled | partial
        }

        if ($startCount !== null) {
            $query['start_count'] = $startCount;
        }

        if ($completions !== null) {
            $query['completions'] = $completions;
        }

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->get($url, $query);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Socpanel editOrder failed: ' . $response->body()
            );
        }

        $body = $response->json();

        if (!is_array($body) || !array_key_exists('ok', $body)) {
            throw new \RuntimeException('Socpanel editOrder returned invalid response');
        }

        return $body;
    }

    /**
     * Fetch orders by comma-separated order IDs.
     *
     * @param array<int|string> $orderIds Order IDs (e.g. remote_order_id)
     * @return array<int, array> Map of order_id => item (keyed by item['id'] when present)
     */
    public function getOrdersByIds(array $orderIds): array
    {
        $orderIds = array_values(array_filter(array_map('intval', $orderIds)));
        if ($orderIds === []) {
            return [];
        }

        $url = $this->baseUrl . '/getOrders';
        $orderIdsString = implode(',', array_map('strval', $orderIds));

        $payload = [
            'order_ids' => $orderIdsString,
            'token'     => $this->token,
        ];

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if (!$response->successful()) {
            $this->throwRequestException($response, $url);
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new \RuntimeException('Socpanel getOrders(order_ids=...) returned non-array body');
        }

        $items = $body['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $byId = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? null;
            if ($id !== null && $id !== '') {
                $byId[(int) $id] = $item;
            }
        }

        return $byId;
    }


    /**
     * @throws \RuntimeException
     */
    private function throwRequestException(Response $response, string $url): void
    {
        $status = $response->status();
        $body = $response->body();

        throw new \RuntimeException(
            "Socpanel API request failed: HTTP {$status} for {$url}. Response: " . substr($body, 0, 500)
        );
    }
}
