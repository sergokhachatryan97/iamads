<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Max\MaxTaskClaimService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MaxTaskClaimController extends Controller
{
    public function __construct(
        private MaxTaskClaimService $claimService
    ) {}

    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_identity' => ['required', 'string'],
            'service_id' => ['required', 'integer'],
        ]);

        $account = $validated['account_identity'];
        $serviceId = (int) $validated['service_id'];

        // Single Redis round-trip: poll throttle + no-work check (global + per-account)
        $noWorkKey = "max:no_work:{$serviceId}";
        $accountNoWorkKey = "max:no_work:account:{$serviceId}:" . md5($account);
        $throttleKey = 'max:poll_throttle:' . md5($account);
        try {
            [$throttleAcquired, $_, $noWork, $accountNoWork] = Redis::pipeline(function ($pipe) use ($throttleKey, $noWorkKey, $accountNoWorkKey) {
                $pipe->set($throttleKey, 1, 'EX', 10, 'NX');
                $pipe->expire($throttleKey, 10);
                $pipe->exists($noWorkKey);
                $pipe->exists($accountNoWorkKey);
            });
            if (! $throttleAcquired) {
                return self::emptyResponse();
            }
            if ($noWork || $accountNoWork) {
                return self::emptyResponse();
            }
        } catch (\Throwable) {
            // Redis down — proceed normally.
        }

        try {
            // Early exit: if eligible orders cache is empty, skip expensive claim
            // and set no-work flag immediately. Prevents CPU spikes when no tasks exist.
            $cacheKey = "max:claim:eligible:s{$serviceId}";
            $eligible = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($eligible !== null && (is_countable($eligible) ? count($eligible) === 0 : empty($eligible))) {
                try {
                    Redis::setex($noWorkKey, random_int(90, 150), 1);
                } catch (\Throwable) {
                }

                return self::emptyResponse();
            }

            $result = $this->claimService->claim($account, $serviceId);

            if ($result === null) {
                try {
                    $hasEligible = $eligible !== null && (is_countable($eligible) ? count($eligible) > 0 : !empty($eligible));

                    if (! $hasEligible) {
                        Redis::setex($noWorkKey, $this->noWorkTtl($serviceId), 1);
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

            return response()->json([
                'id' => $result['task_id'],
                'url' => $result['link'],
                'action' => $result['action'],
                'comment_text' => $result['comment_text'] ?? null,
            ]);
        } catch (QueryException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'max_user_connections')
                || str_contains($msg, 'too many connections')
                || str_contains($msg, 'Too many connections')
                || str_contains($msg, 'gone away')) {
                Log::warning('MaxTaskClaim: DB pool exhausted', ['error' => $msg]);

                return self::emptyResponse();
            }
            throw $e;
        } finally {
            DB::disconnect();
        }
    }

    private static function emptyResponse(): JsonResponse
    {
        return response()->json(['ok' => true, 'count' => 0, 'tasks' => []]);
    }

    /**
     * No work TTL: long when no eligible orders exist, short when work
     * exists but this account couldn't claim (cooldown/dedup).
     * Max uses pull-model only — check the eligible orders app cache.
     */
    private function noWorkTtl(int $serviceId): int
    {
        // Check if eligible orders exist in cache.
        // If cache is empty or has no orders, there's genuinely no work.
        $cacheKey = "max:claim:eligible:s{$serviceId}";
        $eligible = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if ($eligible === null || (is_countable($eligible) && count($eligible) === 0)) {
            return random_int(90, 150); // no work — jittered to prevent thundering herd
        }

        // Work exists but account couldn't claim → traffic-based TTL
        try {
            $hourKey = 'max:claim_attempts:' . $serviceId . ':' . now()->format('Y-m-d-H');
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
