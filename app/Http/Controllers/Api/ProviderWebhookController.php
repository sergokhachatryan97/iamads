<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProviderWebhookController extends Controller
{
    /**
     * Handle incoming provider webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1) Security: Validate webhook secret
        $providedSecret = $request->header('X-Provider-Webhook-Secret');
        $expectedSecret = config('services.provider.webhook_secret');

        if (empty($expectedSecret)) {
            Log::error('Provider webhook secret not configured');
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        if (empty($providedSecret) || !hash_equals($expectedSecret, $providedSecret)) {
            Log::warning('Invalid provider webhook secret', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return response()->json(['error' => 'Invalid webhook secret'], 403);
        }

        // 2) Parse JSON payload
        try {
            $payload = $request->json()->all();
        } catch (\Throwable $e) {
            Log::warning('Invalid JSON in provider webhook', [
                'error' => $e->getMessage(),
                'body_preview' => substr($request->getContent(), 0, 500),
            ]);
            return response()->json(['error' => 'Invalid JSON payload'], 422);
        }

        if (empty($payload)) {
            Log::warning('Empty payload in provider webhook');
            return response()->json(['error' => 'Empty payload'], 422);
        }

        // 3) Extract provider order ID
        $providerOrderId = $this->extractProviderOrderId($payload);

        if (!$providerOrderId) {
            Log::warning('Provider order ID not found in webhook payload', [
                'payload_keys' => array_keys($payload),
            ]);
            return response()->json(['error' => 'Provider order ID not found'], 422);
        }

        // 4) Find local order
        $order = Order::where('provider_order_id', $providerOrderId)->first();

        if (!$order) {
            Log::warning('Order not found for provider webhook', [
                'provider_order_id' => $providerOrderId,
                'payload_preview' => json_encode(array_slice($payload, 0, 5)),
            ]);
            return response()->json(['error' => 'Order not found'], 404);
        }

        // 5) Save raw payload and set webhook received timestamp
        // Clear any polling locks since webhook is authoritative
        $order->update([
            'provider_webhook_payload' => $payload,
            'provider_webhook_received_at' => now(),
            'provider_webhook_last_error' => null,
            'provider_status_sync_lock_at' => null,
            'provider_status_sync_lock_owner' => null,
        ]);

        // 6) Extract and update fields
        try {
            $this->updateOrderFromWebhook($order, $payload);

            Log::info('Provider webhook processed successfully', [
                'order_id' => $order->id,
                'provider_order_id' => $providerOrderId,
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            $order->update([
                'provider_webhook_last_error' => $e->getMessage(),
            ]);

            Log::error('Error processing provider webhook', [
                'order_id' => $order->id,
                'provider_order_id' => $providerOrderId,
                'error' => $e->getMessage(),
                'payload_preview' => json_encode(array_slice($payload, 0, 5)),
            ]);

            return response()->json(['error' => 'Failed to process webhook'], 500);
        }
    }

    /**
     * Extract provider order ID from payload.
     */
    protected function extractProviderOrderId(array $data): ?string
    {
        $keys = ['provider_order_id', 'order_id', 'order', 'id', 'data.order_id', 'data.id'];

        foreach ($keys as $key) {
            $value = $this->getNestedValue($data, $key);
            if ($value !== null) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Get nested value from array using dot notation.
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
     * Extract a value from payload using multiple possible keys.
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
     * Update order fields from webhook payload.
     */
    protected function updateOrderFromWebhook(Order $order, array $payload): void
    {
        $updateData = [];

        // Extract status
        $providerStatus = $this->extractValue($payload, ['status', 'state', 'data.status']);
        if ($providerStatus !== null) {
            $updateData['status'] = $this->mapProviderStatus((string) $providerStatus);
        }

        // Extract start_count
        $startCount = $this->extractValue($payload, ['start_count', 'startCount', 'data.start_count']);
        if ($startCount !== null) {
            $updateData['start_count'] = max(0, (int) $startCount);
        }

        // Extract delivered and remains
        $quantity = (int) $order->quantity;
        $deliveredFromProvider = $this->extractValue($payload, ['delivered', 'completed', 'data.delivered']);
        $remainsFromProvider = $this->extractValue($payload, ['remains', 'rem', 'data.remains']);

        // Ensure consistency: clamp values and compute missing ones
        if ($deliveredFromProvider !== null) {
            $delivered = max(0, min($quantity, (int) $deliveredFromProvider));
            $updateData['delivered'] = $delivered;

            if ($remainsFromProvider === null) {
                $updateData['remains'] = max(0, $quantity - $delivered);
            }
        }

        if ($remainsFromProvider !== null) {
            $remains = max(0, min($quantity, (int) $remainsFromProvider));
            $updateData['remains'] = $remains;

            if ($deliveredFromProvider === null) {
                $updateData['delivered'] = max(0, $quantity - $remains);
            }
        }

        // If both provided, ensure consistency
        if ($deliveredFromProvider !== null && $remainsFromProvider !== null) {
            $delivered = max(0, min($quantity, (int) $deliveredFromProvider));
            $remains = max(0, min($quantity, (int) $remainsFromProvider));
            // Ensure delivered + remains <= quantity
            if ($delivered + $remains > $quantity) {
                $delivered = min($delivered, $quantity);
                $remains = $quantity - $delivered;
            }
            $updateData['delivered'] = $delivered;
            $updateData['remains'] = $remains;
        }

        // Update order if we have any changes
        if (!empty($updateData)) {
            $order->update($updateData);
        }
    }

    /**
     * Map provider status to local status.
     */
    protected function mapProviderStatus(string $providerStatus): string
    {
        $providerStatus = strtolower(trim($providerStatus));

        $statusMap = [
            'pending' => Order::STATUS_PENDING,
            'processing' => Order::STATUS_PROCESSING,
            'in_progress' => Order::STATUS_IN_PROGRESS,
            'in-progress' => Order::STATUS_IN_PROGRESS,
            'completed' => Order::STATUS_COMPLETED,
            'complete' => Order::STATUS_COMPLETED,
            'partial' => Order::STATUS_PARTIAL,
            'canceled' => Order::STATUS_CANCELED,
            'cancelled' => Order::STATUS_CANCELED,
            'failed' => Order::STATUS_FAIL,
            'fail' => Order::STATUS_FAIL,
        ];

        return $statusMap[$providerStatus] ?? Order::STATUS_PROCESSING;
    }
}

