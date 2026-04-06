<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates provider-style API requests via `key` in request body.
 * Resolves the client by api_key when api_enabled=true and status is active.
 * Sets the authenticated client on the request as 'api_client'.
 */
class AuthenticateProviderApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('Provider API incoming request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'content_type' => $request->header('Content-Type'),
            'headers' => $request->headers->all(),
            'all' => $request->all(),
            'raw' => $request->getContent(),
            'key_input' => $request->input('key'),
            'action_input' => $request->input('action'),
        ]);
        
        $apiKey = $request->input('key');

        if (!$apiKey || trim((string) $apiKey) === '') {
            return response()->json(['error' => 'Missing or invalid API key'], 401);
        }

        $client = Client::query()
            ->where('api_key', trim((string) $apiKey))
            ->where('api_enabled', true)
            ->first();

        if (!$client) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        if ($client->status === 'suspended' || $client->suspended_at !== null) {
            return response()->json(['error' => 'Account is suspended'], 403);
        }

        // Update last used timestamp
        $client->timestamps = false;
        $client->update(['api_last_used_at' => now()]);
        $client->timestamps = true;

        $request->attributes->set('api_client', $client);
        $request->setUserResolver(fn () => $client);

        return $next($request);
    }
}
