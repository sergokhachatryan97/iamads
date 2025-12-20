<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientLoginLog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Jenssegers\Agent\Agent;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class ClientRegisteredUserController extends Controller
{
    /**
     * Display the client registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming client registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.Client::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $client = Client::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'staff_id' => null, // Default to null - will be assigned by staff later
            'balance' => 0,
            'spent' => 0,
            'discount' => 0,
            'status' => 'active', // Default status for new clients
        ]);

        event(new Registered($client));

        Auth::guard('client')->login($client);

        // Update last_auth timestamp
        $client->update(['last_auth' => now()]);

        // Create login log entry for registration
        $this->createLoginLog($client, $request);

        return redirect(route('dashboard', absolute: false));
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
            // Using Laravel's HTTP client with timeout to avoid blocking registration
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
            // Log error but don't fail the registration
            \Log::warning('Failed to get location from IP ' . $ip . ': ' . $e->getMessage());
        }

        return [];
    }
}
