<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\App\AppTaskClaimService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * App performer claim: performer requests a task, backend returns one task with link and order info.
 */
class AppTaskClaimController extends Controller
{
    public function __construct(
        private AppTaskClaimService $claimService
    ) {}

    /**
     * Claim one task. GET /getOrder. Requires account_identity (query or body).
     */
    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_identity' => ['required', 'string', 'max:255'],
        ]);

        try {
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

            $response = [
                'ok' => true,
                'count' => 1,
                'task_id' => $payload['task_id'],
                'link' => $payload['link'],
                'link_hash' => $payload['link_hash'] ?? null,
                'action' => $payload['action'] ?? 'download',
                'mode' => $payload['mode'] ?? 'single',
                'steps' => $payload['steps'] ?? null,
                'order' => [
                    'id' => $payload['order']['id'],
                    'quantity' => $payload['order']['quantity'] ?? null,
                    'delivered' => $payload['order']['delivered'] ?? null,
                    'remains' => $payload['order']['remains'] ?? null,
                    'target_quantity' => $payload['order']['target_quantity'] ?? null,
                    'dripfeed_enabled' => $payload['order']['dripfeed_enabled'] ?? false,
                    'service_description' => $payload['service']['description'] ?? '',
                    'service_name' => $payload['service']['name'] ?? null,
                    'service_id' => $payload['service']['id'] ?? null,
                    'category' => $payload['category'] ?? null,
                ],
                'service' => $payload['service'] ?? null,
            ];
            if (!empty($payload['comment_text'])) {
                $response['comment_text'] = $payload['comment_text'];
            }
            if (isset($payload['star_rating'])) {
                $response['star_rating'] = $payload['star_rating'];
            }
            return response()->json($response);
        } catch (QueryException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'max_user_connections')
                || str_contains($msg, 'too many connections')
                || str_contains($msg, 'Too many connections')
                || str_contains($msg, 'gone away')) {
                Log::warning('AppTaskClaim: DB pool exhausted', ['error' => $msg]);

                return response()->json([
                    'ok' => true,
                    'count' => 0,
                    'tasks' => [],
                    'task_id' => null,
                    'link' => null,
                    'order' => null,
                ]);
            }
            throw $e;
        } finally {
            DB::disconnect();
        }
    }
}
