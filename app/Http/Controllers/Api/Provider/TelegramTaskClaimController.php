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

        // Two-tier no-work cache:
        //  - service-wide (tg:no_work:{scope}:{serviceId}) — set only when the
        //    queue is truly empty; blocks every phone for 90-150s until preassign
        //    refills and clears it.
        //  - per-phone   (tg:no_work:phone:{phone}:{scope}:{serviceId}) — set
        //    when the queue has tasks but THIS specific phone failed to claim
        //    (already-member / cap / cooldown). Prevents hot-looping by this one
        //    phone without starving every other phone of a full queue.
        $noWorkKey      = "tg:no_work:{$scope}:{$serviceId}";
        $phoneNoWorkKey = "tg:no_work:phone:{$phone}:{$scope}:{$serviceId}";
        $hourKey        = 'tg:claim_attempts:' . $serviceId . ':' . now()->format('Y-m-d-H');
        try {
            [$_, $_, $noWork, $phoneNoWork] = Redis::pipeline(function ($pipe) use ($hourKey, $noWorkKey, $phoneNoWorkKey, $phone) {
                $pipe->pfadd($hourKey, [$phone]);
                $pipe->expire($hourKey, 7200);
                $pipe->exists($noWorkKey);
                $pipe->exists($phoneNoWorkKey);
            });
            if ($noWork || $phoneNoWork) {
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
            // Only fall back to the expensive pull path when the queue still has
            // items (phone was rejected for this specific task) or a pull-gate
            // flag is set. When the queue is truly empty, skip pull entirely —
            // preassign will refill within 30s, and running the full claim
            // pipeline for every request in the meantime causes CPU spikes
            // (expensive DB queries + row locks with no results).
            if ($task === null) {
                $queueKey = "tg:service_queue:{$scope}:{$serviceId}";
                $queueLen = 0;
                try {
                    $queueLen = (int) Redis::llen($queueKey);
                } catch (\Throwable) {
                }

                if ($queueLen > 0) {
                    // Queue has tasks but push-model couldn't claim — try pull path
                    $pulled = $this->claimService->claimForPhone($phone, 1, $scope, $serviceId);
                    $task   = $pulled[0] ?? null;
                }
            }

            if ($task === null) {
                try {
                    $queueKey = $queueKey ?? "tg:service_queue:{$scope}:{$serviceId}";
                    $queueLen = $queueLen ?? (int) Redis::llen($queueKey);

                    if ($queueLen === 0) {
                        // Queue empty → service-wide block until preassign refills.
                        Redis::setex($noWorkKey, random_int(90, 150), 1);
                    } else {
                        // Queue has tasks but this phone couldn't claim — mark
                        // only this phone so other phones can still draw from
                        // the full queue. Short TTL so the phone retries soon
                        // after its cooldown / cap resets.
                        Redis::setex($phoneNoWorkKey, 10, 1);
                    }
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
