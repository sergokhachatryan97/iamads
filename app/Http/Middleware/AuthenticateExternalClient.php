<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate external API clients via X-Client-Token header (shared secret).
 * Sets request attribute 'external_client' to the client name associated with the token.
 */
class AuthenticateExternalClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokens = config('services.external_clients.tokens');

        if (!is_array($tokens) || empty($tokens)) {
            return response()->json([
                'ok' => false,
                'error' => 'External client authentication not configured',
            ], 500);
        }

        $providedToken = $request->header('X-Client-Token');

        if ($providedToken === null || $providedToken === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid client token',
            ], 401);
        }

        $clientName = null;
        foreach ($tokens as $token => $name) {
            if (hash_equals((string) $token, $providedToken)) {
                $clientName = $name;
                break;
            }
        }

        if ($clientName === null) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid client token',
            ], 401);
        }

        $request->attributes->set('external_client', $clientName);

        return $next($request);
    }
}
