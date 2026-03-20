<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Client API access management – enable/disable, view/regenerate API key.
 */
class ApiController extends Controller
{
    public function index(): View
    {
        $client = Auth::guard('client')->user();
        $baseUrl = config('app.url') . '/api/external';

        return view('client.api.index', [
            'client' => $client,
            'baseUrl' => $baseUrl,
            'apiKeyMasked' => $client->api_key ? substr($client->api_key, 0, 8) . '…' . substr($client->api_key, -4) : null,
        ]);
    }

    public function revealKey(): JsonResponse
    {
        $client = Auth::guard('client')->user();
        if (!$client->api_enabled || !$client->api_key) {
            return response()->json(['error' => 'API key not available'], 404);
        }
        return response()->json(['api_key' => $client->api_key]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();
        $enabled = (bool) $request->input('api_enabled');

        $client->api_enabled = $enabled;
        if ($enabled && !$client->api_key) {
            $client->api_key = 'sk_' . Str::random(48);
            $client->api_key_generated_at = now();
        }
        if (!$enabled) {
            $client->api_key = null;
            $client->api_key_generated_at = null;
        }
        $client->save();

        return redirect()
            ->route('client.api.index')
            ->with('status', $enabled ? 'api-enabled' : 'api-disabled');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        if (!$client->api_enabled) {
            return redirect()
                ->route('client.api.index')
                ->with('error', 'Enable API access first.');
        }

        $client->api_key = 'sk_' . Str::random(48);
        $client->api_key_generated_at = now();
        $client->save();

        return redirect()
            ->route('client.api.index')
            ->with('status', 'api-key-regenerated');
    }
}
