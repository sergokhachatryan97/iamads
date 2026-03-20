<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates external API requests via Authorization: Bearer <api_key>.
 * Resolves the client by api_key when api_enabled=true.
 * Sets the authenticated client on the request as 'api_client'.
 */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'ok' => false,
                'error' => 'Missing or invalid Authorization header. Use: Authorization: Bearer <api_key>',
            ], 401);
        }

        $apiKey = trim(substr($header, 7));

        if ($apiKey === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid API key',
            ], 401);
        }

        $client = Client::query()
            ->where('api_key', $apiKey)
            ->where('api_enabled', true)
            ->first();

        if (!$client) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid or inactive API key',
            ], 401);
        }

        // Update last used timestamp (fire-and-forget, do not block response)
        $client->timestamps = false;
        $client->update(['api_last_used_at' => now()]);
        $client->timestamps = true;

        $request->attributes->set('api_client', $client);
        $request->setUserResolver(fn () => $client);

        return $next($request);
    }
}
