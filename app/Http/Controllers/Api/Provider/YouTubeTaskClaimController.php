<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\YouTube\YouTubeTaskClaimService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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

        $account = $validated['account_identity'];

        // Single Redis round-trip: poll throttle + no-work check (global + per-account) + HyperLogLog
        $noWorkKey = 'yt:no_work';
        $accountNoWorkKey = 'yt:no_work:account:' . md5($account);
        $throttleKey = 'yt:poll_throttle:' . md5($account);
        $hourKey = 'yt:claim_attempts:' . now()->format('Y-m-d-H');
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
            $payload = $this->claimService->claim($account);

            if ($payload === null) {
                try {
                    // Check if there are any eligible orders at all.
                    // If none — set global no-work flag (blocks all accounts).
                    // If there are orders but this account couldn't claim — set
                    // per-account no-work flag (only blocks this account, short TTL).
                    $eligible = \Illuminate\Support\Facades\Cache::get('yt:claim:eligible');
                    $hasEligible = $eligible !== null && (is_countable($eligible) ? count($eligible) > 0 : !empty($eligible));

                    if (! $hasEligible) {
                        $ttl = $this->noWorkTtl();
                        Redis::setex($noWorkKey, $ttl, 1);
                    } else {
                        // Orders exist but this account can't claim any — block
                        // only this account for 30s so it doesn't hot-loop through
                        // the full claim pipeline every 10s.
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

    /**
     * No-work TTL: long when no eligible orders, short when work exists.
     * YouTube has no service_id filter — single global eligible cache.
     */
    private function noWorkTtl(): int
    {
        $eligible = \Illuminate\Support\Facades\Cache::get('yt:claim:eligible');

        if ($eligible === null || (is_countable($eligible) && count($eligible) === 0)) {
            return random_int(90, 150);
        }

        try {
            $hourKey = 'yt:claim_attempts:' . now()->format('Y-m-d-H');
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
