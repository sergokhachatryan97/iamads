<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramTaskClaimService;
use App\Support\TelegramPremiumTemplateScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Account-driven claim: performer sends phone, core returns tasks for that phone.
 * Endpoint: POST /api/provider/telegram/tasks/claim
 */
class TelegramTaskClaimController extends Controller
{
    public function __construct(
        private TelegramTaskClaimService $claimService
    ) {}

    /**
     * Claim tasks for the given performer phone (non-premium Telegram templates only).
     */
    public function claim(Request $request): JsonResponse
    {
        return $this->claimByScope($request, TelegramPremiumTemplateScope::SCOPE_DEFAULT);
    }

    /**
     * Claim tasks for the given performer phone (premium Telegram templates only).
     */
    public function claimPremium(Request $request): JsonResponse
    {
        return $this->claimByScope($request, TelegramPremiumTemplateScope::SCOPE_PREMIUM);
    }

    private static function emptyResponse(): JsonResponse
    {
        return response()->json(['ok' => true, 'count' => 0, 'tasks' => []]);
    }

    private function claimByScope(Request $request, string $scope): JsonResponse
    {
        $validated = $request->validate([
            'account_identity' => ['required', 'string'],
            'service_id' => ['required', 'integer'],
        ]);

        $phone = $validated['account_identity'];
        $serviceId = (int) $validated['service_id'];

        // ── No-work short-circuit ──────────────────────────────────────
        // When a previous poll for this (service, scope) found zero eligible
        // orders, we cache that fact for a few seconds. Subsequent polls skip
        // the full claim pipeline (no DB transaction, no Redis locks).
        // A successful claim clears the flag so new work is picked up instantly.
        $noWorkKey = "tg:no_work:{$scope}:{$serviceId}";
        try {
            if (Redis::exists($noWorkKey)) {
                return self::emptyResponse();
            }
        } catch (\Throwable) {
            // Redis down — proceed normally.
        }

        try {
            // ── Push-model path (fast): LPOP from pre-assigned Redis queue ──────
            // PreassignTelegramTasksJob fills tg:service_queue:{scope}:{serviceId}
            // every 30s. When the queue has tasks this path does:
            //   LPOP (Redis) + UPDATE WHERE status=pending (single row, no locks).
            // No transaction, no FOR UPDATE, no deadlocks.
            $task = $this->claimService->claimFromQueue($phone, $scope, $serviceId);

            // ── Pull-model fallback: full claim pipeline ─────────────────────────
            // Reached when the pre-assigned queue is empty (background job hasn't
            // run yet, or all tasks were just consumed). Keeps the system working
            // during the transition period and for low-volume services.
            if ($task === null) {
                $pulled = $this->claimService->claimForPhone($phone, 1, $scope, $serviceId);
                $task   = $pulled[0] ?? null;
            }

            if ($task === null) {
                // Cache "no work" for this service+scope for a short window.
                // TTL of 15s: reduces Redis/DB load 5× on idle services.
                // New orders are still picked up quickly because a successful
                // claim always clears this key immediately (see Redis::del above).
                try {
                    Redis::setex($noWorkKey, 30, 1);
                } catch (\Throwable) {
                }

                return self::emptyResponse();
            }

            // Work was found — clear the no-work flag for this service
            // so other accounts pick up remaining tasks without delay.
            try {
                Redis::del($noWorkKey);
            } catch (\Throwable) {
            }

            $action = $task['action'] ?? 'subscribe';

            return response()->json([
                'id' => $task['task_id'],
                'url' => $task['link'],
                'action' => $action,
            ]);
        } catch (QueryException $e) {
            // max_user_connections / connection refused — return empty so the
            // performer just retries instead of crashing the request.
            $msg = $e->getMessage();
            if (str_contains($msg, 'max_user_connections')
                || str_contains($msg, 'too many connections')
                || str_contains($msg, 'Too many connections')
                || str_contains($msg, 'gone away')) {
                Log::warning('TelegramTaskClaim: DB pool exhausted', ['error' => $msg]);

                return response()->json(['ok' => true, 'count' => 0, 'tasks' => []]);
            }
            throw $e;
        } finally {
            // Release the MySQL connection back to the pool immediately so it
            // doesn't sit idle (Sleep state) waiting for the next request on
            // the same PHP-FPM worker. Critical mitigation for max_user_connections.
            DB::disconnect();
        }
    }
}
