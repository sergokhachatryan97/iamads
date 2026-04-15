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

        // Single Redis round-trip: poll throttle + no-work check
        $noWorkKey = "max:no_work:{$serviceId}";
        $throttleKey = 'max:poll_throttle:' . md5($account);
        try {
            [$throttleAcquired, $_, $noWork] = Redis::pipeline(function ($pipe) use ($throttleKey, $noWorkKey) {
                $pipe->set($throttleKey, 1, 'EX', 10, 'NX');
                $pipe->expire($throttleKey, 10);
                $pipe->exists($noWorkKey);
            });
            if (! $throttleAcquired) {
                return self::emptyResponse();
            }
            if ($noWork) {
                return self::emptyResponse();
            }
        } catch (\Throwable) {
            // Redis down — proceed normally.
        }

        try {
            $result = $this->claimService->claim($account, $serviceId);

            if ($result === null) {
                // No work found — cache with dynamic TTL.
                // No queue for Max (pull-only), so check eligible orders cache.
                try {
                    $ttl = $this->noWorkTtl($serviceId);
                    Redis::setex($noWorkKey, $ttl, 1);
                } catch (\Throwable) {
                }

                return self::emptyResponse();
            }

            // Work found — clear no-work flag
            try {
                Redis::del($noWorkKey);
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
