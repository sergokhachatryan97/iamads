<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Auth\ClientLoginRequest;
use App\Models\ClientLoginLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Jenssegers\Agent\Agent;
use Illuminate\View\View;

class ClientAuthenticatedSessionController extends Controller
{
    /**
     * Display the client login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming client authentication request.
     */
    public function store(ClientLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Get authenticated client
        $client = Auth::guard('client')->user();

        if ($client) {
            // Check if client is suspended
            if ($client->status === 'suspended') {
                Auth::guard('client')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your account is suspended. You cannot access the panel.']);
            }

            // Update last_auth timestamp
            $client->update(['last_auth' => now()]);

            // Create login log entry
            $this->createLoginLog($client, $request);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Create a login log entry for the client.
     */
    private function createLoginLog($client, Request $request): void
    {
        $agent = new Agent();
        $userAgent = $request->userAgent();

        // Parse user agent if available
        if ($userAgent) {
            $agent->setUserAgent($userAgent);
        }

        // Determine device type
        $deviceType = 'unknown';
        if ($agent->isRobot()) {
            $deviceType = 'bot';
        } elseif ($agent->isMobile()) {
            $deviceType = 'mobile';
        } elseif ($agent->isTablet()) {
            $deviceType = 'tablet';
        } elseif ($agent->isDesktop()) {
            $deviceType = 'desktop';
        }

        // Get device name
        $deviceName = $agent->device();

        // Get IP address
        $ip = $request->ip();

        // Get location data from IP
        $locationData = $this->getLocationFromIp($ip);

        ClientLoginLog::create([
            'client_id' => $client->id,
            'signed_in_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'os' => $agent->platform(),
            'browser' => $agent->browser(),
            'device_name' => $deviceName,
            'country' => $locationData['country'] ?? null,
            'city' => $locationData['city'] ?? null,
            'lat' => $locationData['lat'] ?? null,
            'lng' => $locationData['lng'] ?? null,
        ]);
    }

    /**
     * Get location data from IP address using free GeoIP service.
     */
    private function getLocationFromIp(string $ip): array
    {
        // Skip local/private IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [];
        }

        try {
            // Use ip-api.com free service (no API key required, 45 requests/minute limit)
            // Using Laravel's HTTP client with timeout to avoid blocking login
            $response = Http::timeout(2)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,message,countryCode,city,lat,lon'
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'country' => $data['countryCode'] ?? null,
                        'city' => $data['city'] ?? null,
                        'lat' => isset($data['lat']) ? (float) $data['lat'] : null,
                        'lng' => isset($data['lon']) ? (float) $data['lon'] : null,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the login
            \Log::warning('Failed to get location from IP ' . $ip . ': ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Destroy an authenticated client session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('client')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
