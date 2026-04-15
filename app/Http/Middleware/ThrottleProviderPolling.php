<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-limits provider polling endpoints by account_identity.
 *
 * When the same account polls faster than the allowed interval, the request
 * is short-circuited with a lightweight empty-tasks JSON response — no DB
 * queries, no claim logic, no PHP-FPM process time wasted.
 *
 * This is the single most effective application-level protection against
 * burst traffic overwhelming nginx worker_connections and PHP-FPM children.
 */
class ThrottleProviderPolling
{
    /**
     * Minimum seconds between polls for the same account_identity.
     * Configurable via PROVIDER_POLL_INTERVAL_SECONDS env var.
     */
    private function intervalSeconds(): int
    {
        return (int) config('services.provider.poll_interval_seconds', 10);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->input('account_identity');

        if (empty($account)) {
            return $next($request);
        }

        $key = 'tg:poll_throttle:'.md5($account);
        $interval = $this->intervalSeconds();

        try {
            // SET NX with TTL — if the key already exists, this account polled
            // within the interval and we return an empty response immediately.
            $acquired = Redis::set($key, 1, 'EX', $interval, 'NX');

            if (! $acquired) {
                return response()->json([
                    'ok' => true,
                    'count' => 0,
                    'tasks' => [],
                ], 200);
            }
        } catch (\Throwable) {
            // If Redis is down, fail open — let the request through.
        }

        return $next($request);
    }
}
