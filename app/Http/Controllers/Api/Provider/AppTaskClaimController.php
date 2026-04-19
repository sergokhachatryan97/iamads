<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\App\AppTaskClaimService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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

        $account = $validated['account_identity'];

        // Single Redis round-trip: poll throttle + no-work check (global + per-account) + HyperLogLog
        $noWorkKey = 'app:no_work';
        $accountNoWorkKey = 'app:no_work:account:' . md5($account);
        $throttleKey = 'app:poll_throttle:' . md5($account);
        $hourKey = 'app:claim_attempts:' . now()->format('Y-m-d-H');
        try {
            [$throttleAcquired, $_, $noWork, $accountNoWork, $_, $_] = Redis::pipeline(function ($pipe) use ($throttleKey, $noWorkKey, $accountNoWorkKey, $hourKey, $account) {
                $pipe->set($throttleKey, 1, 'EX', 10, 'NX');
                $pipe->expire($throttleKey, 10);
                $pipe->exists($noWorkKey);
                $pipe->exists($accountNoWorkKey);
                $pipe->pfadd($hourKey, [$account]);
                $pipe->expire($hourKey, 7200);
            });
            if (! $throttleAcquired) {
                return self::emptyResponse();
            }
            if ($noWork || $accountNoWork) {
                return self::emptyResponse();
            }
        } catch (\Throwable) {
        }

        try {
            // Early exit: if eligible orders cache is empty, skip expensive claim
            // and set no-work flag immediately. Prevents CPU spikes when no tasks exist.
            $eligible = \Illuminate\Support\Facades\Cache::get('app:claim:eligible');
            if ($eligible !== null && (is_countable($eligible) ? count($eligible) === 0 : empty($eligible))) {
                try {
                    Redis::setex($noWorkKey, random_int(90, 150), 1);
                } catch (\Throwable) {
                }

                return self::emptyResponse();
            }

            $payload = $this->claimService->claim($account);

            if ($payload === null) {
                try {
                    $hasEligible = $eligible !== null && (is_countable($eligible) ? count($eligible) > 0 : !empty($eligible));

                    if (! $hasEligible) {
                        Redis::setex($noWorkKey, $this->noWorkTtl(), 1);
                    } else {
                        // Orders exist but this account can't claim — block only
                        // this account so others can still attempt.
                        Redis::setex($accountNoWorkKey, 30, 1);
                    }
                } catch (\Throwable) {
                }

                return self::emptyResponse();
            }

            // Work found — clear both no-work flags
            try {
                Redis::del($noWorkKey, $accountNoWorkKey);
            } catch (\Throwable) {
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

                return self::emptyResponse();
            }
            throw $e;
        } finally {
            DB::disconnect();
        }
    }

    private static function emptyResponse(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'count' => 0,
            'tasks' => [],
            'task_id' => null,
            'link' => null,
            'order' => null,
        ]);
    }

    private function noWorkTtl(): int
    {
        $eligible = \Illuminate\Support\Facades\Cache::get('app:claim:eligible');

        if ($eligible === null || (is_countable($eligible) && count($eligible) === 0)) {
            return random_int(90, 150);
        }

        try {
            $hourKey = 'app:claim_attempts:' . now()->format('Y-m-d-H');
            $accounts = (int) Redis::pfcount($hourKey);
        } catch (\Throwable) {
            return 10;
        }

        if ($accounts >= 50000) {
            return 5;
        }
        if ($accounts >= 5000) {
            return 10;
        }

        return 30;
    }
}
