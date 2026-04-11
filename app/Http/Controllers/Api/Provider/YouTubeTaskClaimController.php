<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\YouTube\YouTubeTaskClaimService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            // Watch-time cooldown error
            if (isset($payload['error']) && isset($payload['retry_after'])) {
                return response()->json([
                    'ok' => false,
                    'error' => $payload['error'],
                    'retry_after' => $payload['retry_after'],
                ], 429);
            }

            $response = [
                'ok' => true,
                'count' => 1,
                'task_id' => $payload['task_id'],
                'link' => $payload['link'],
                'link_hash' => $payload['link_hash'] ?? null,
                'action' => $payload['action'] ?? 'view',
                'mode' => $payload['mode'] ?? 'single',
                'steps' => $payload['steps'] ?? null,
                'target' => $payload['target'] ?? null,
                'order' => [
                    'id' => $payload['order']['id'],
                    'quantity' => $payload['order']['quantity'] ?? null,
                    'delivered' => $payload['order']['delivered'] ?? null,
                    'remains' => $payload['order']['remains'] ?? null,
                    'target_quantity' => $payload['order']['target_quantity'] ?? null,
                    'dripfeed_enabled' => $payload['order']['dripfeed_enabled'] ?? false,
                    'service_description' => $payload['service']['description'] ??  '',
                    'service_name' => $payload['service']['name'] ?? null,
                    'service_id' => $payload['service']['id'] ?? null,
                    'category' => $payload['category'] ?? null,
                ],
                'service' => $payload['service'] ?? null,
            ];
            if (!empty($payload['comment_text'])) {
                $response['comment_text'] = $payload['comment_text'];
            }
            if (isset($payload['watch_time_seconds'])) {
                $response['watch_time_seconds'] = $payload['watch_time_seconds'];
            }
            return response()->json($response);
        } catch (QueryException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'max_user_connections')
                || str_contains($msg, 'too many connections')
                || str_contains($msg, 'Too many connections')
                || str_contains($msg, 'gone away')) {
                Log::warning('YouTubeTaskClaim: DB pool exhausted', ['error' => $msg]);

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
            // Release the MySQL connection back to the pool immediately so it
            // doesn't sit idle (Sleep state) on the PHP-FPM worker. Critical
            // mitigation for max_user_connections.
            DB::disconnect();
        }
    }
}
