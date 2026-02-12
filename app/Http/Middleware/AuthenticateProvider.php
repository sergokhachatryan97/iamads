<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to authenticate provider requests via shared secret header.
 */
class AuthenticateProvider
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedToken = $request->header('X-Provider-Token');
        $expectedToken = config('services.provider.token');

        if (empty($expectedToken)) {
            return response()->json([
                'ok' => false,
                'error' => 'Provider authentication not configured',
            ], 500);
        }

        if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid provider token',
            ], 401);
        }

        return $next($request);
    }
}
