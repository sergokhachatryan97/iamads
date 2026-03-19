<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\YouTube\YouTubeTaskClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * YouTube performer claim: performer requests a task, backend returns one task with link and order info.
 * Performer only sends phone number (account_identity). Uniqueness: (account_identity, order, link).
 */
class YouTubeTaskClaimController extends Controller
{
    public function __construct(
        private YouTubeTaskClaimService $claimService
    ) {}

    /**
     * Claim one task. GET /getOrder or GET /claim. Requires account_identity (phone number, query or body).
     * Response includes task_id, link, order (id, service_description, ...).
     */
    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_identity' => ['required', 'string', 'max:255'],
        ]);
        $payload = $this->claimService->claim($validated['account_identity']);

        if ($payload === null) {
            return response()->json([
                'ok' => true,
                'count' => 0,
                'tasks' => [],
                'task_id' => null,
                'link' => null,
                'order' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'count' => 1,
            'task_id' => $payload['task_id'],
            'link' => $payload['link'],
            'link_hash' => $payload['link_hash'] ?? null,
            'action' => $payload['action'] ?? 'view',
            'target' => $payload['target'] ?? null,
            'order' => [
                'id' => $payload['order']['id'],
                'quantity' => $payload['order']['quantity'] ?? null,
                'delivered' => $payload['order']['delivered'] ?? null,
                'remains' => $payload['order']['remains'] ?? null,
                'target_quantity' => $payload['order']['target_quantity'] ?? null,
                'dripfeed_enabled' => $payload['order']['dripfeed_enabled'] ?? false,
                'service_description' => $payload['service']['service_description'] ?? $payload['service']['description'] ?? $payload['service']['name'] ?? '',
                'service_name' => $payload['service']['name'] ?? null,
                'service_id' => $payload['service']['id'] ?? null,
                'category' => $payload['category'] ?? null,
            ],
            'service' => $payload['service'] ?? null,
        ]);
    }
}
