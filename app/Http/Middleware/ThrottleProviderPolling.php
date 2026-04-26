<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-limits provider /getOrder polling by provider + account + service.
 *
 * Key: provider:account_identity:service_id (when service_id is present)
 *      provider:account_identity            (when service_id is absent, e.g. YouTube/App)
 *
 * This prevents the same account from hammering the same service endpoint,
 * but does NOT block the same account from claiming tasks for other services.
 *
 * When throttled, returns a lightweight empty-tasks JSON response — no DB
 * queries, no claim logic, no PHP-FPM process time wasted.
 */
class ThrottleProviderPolling
{
    /**
     * Minimum seconds between polls for the same account+service combo.
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

        // Derive provider from route prefix: /provider/telegram/... → telegram
        $provider = $request->segment(2) ?? 'unknown';

        // Include service_id when present (Telegram, Max send it; YouTube, App don't)
        $serviceId = $request->input('service_id');

        $keyParts = "poll:{$provider}:{$account}";
        if ($serviceId !== null && $serviceId !== '') {
            $keyParts .= ":{$serviceId}";
        }

        $key = 'throttle:' . md5($keyParts);
        $interval = $this->intervalSeconds();

        try {
            // SET NX with TTL — if the key already exists, this account+service
            // polled within the interval and we return empty response immediately.
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
