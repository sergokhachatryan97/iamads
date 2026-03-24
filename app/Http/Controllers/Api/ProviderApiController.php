<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProviderApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Provider-style API (Socpanel / Perfect Panel compatible).
 * Single endpoint: POST /api/v2 with key + action in body.
 */
class ProviderApiController extends Controller
{
    public function __construct(
        private ProviderApiService $providerApiService
    ) {}

    /**
     * Handle provider API request.
     * POST /api/v2
     */
    public function handle(Request $request): JsonResponse
    {
        $action = $request->input('action');
        $client = $request->attributes->get('api_client');

        if (!$action || trim((string) $action) === '') {
            $this->logProviderApi($client?->id, null, false, null, 'missing_action');
            return $this->errorResponse('Missing action', 400);
        }

        $action = strtolower(trim((string) $action));

        try {
            $result = match ($action) {
                'services' => $this->handleServices($client),
                'add' => $this->handleAdd($request, $client),
                'status' => $this->handleStatus($request, $client),
                'balance' => $this->handleBalance($client),
                default => null,
            };
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->logProviderApi($client->id, $action, false, null, $message);
            return $this->errorResponse($message, 422);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'Order not found') {
                $this->logProviderApi($client->id, $action, false, null, 'order_not_found');
                return $this->errorResponse('Order not found', 404);
            }
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Provider API error', [
                'action' => $action,
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->logProviderApi($client->id, $action, false, null, $e->getMessage());
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }

        if ($result === null) {
            $this->logProviderApi($client->id, $action, false, null, 'unknown_action');
            return $this->errorResponse('Unknown action', 400);
        }

        $orderId = $result['order'] ?? null;
        $this->logProviderApi($client->id, $action, true, $orderId);

        return response()->json($result);
    }

    private function handleServices($client): array
    {
        return $this->providerApiService->services($client);
    }

    private function handleAdd(Request $request, $client): array
    {
        $validated = $request->validate([
            'service' => ['required', 'integer', 'min:1'],
            'link' => ['required', 'string', 'max:2048'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'speed_tier' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'string', 'max:255'],
        ]);

        $link = trim((string) $validated['link']);
        if (!str_starts_with($link, 'http://') && !str_starts_with($link, 'https://')) {
            $link = 'https://' . $link;
        }
        $validated['link'] = $link;

        return $this->providerApiService->add($client, $validated);
    }

    private function handleStatus(Request $request, $client): array
    {
        $validated = $request->validate([
            'order' => ['required'],
        ]);

        $orderIdOrExternalId = $validated['order'];
        $result = $this->providerApiService->status($client, $orderIdOrExternalId);

        if ($result === null) {
            throw new \RuntimeException('Order not found');
        }

        return $result;
    }

    private function handleBalance($client): array
    {
        return $this->providerApiService->balance($client);
    }

    private function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }

    private function logProviderApi(?int $clientId, ?string $action, bool $success, ?int $orderId, ?string $errorReason = null): void
    {
        Log::channel('single')->info('Provider API', [
            'client_id' => $clientId,
            'action' => $action,
            'success' => $success,
            'order_id' => $orderId,
            'error' => $errorReason,
        ]);
    }
}
