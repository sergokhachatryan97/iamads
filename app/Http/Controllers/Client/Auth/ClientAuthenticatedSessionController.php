<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Auth\ClientLoginRequest;
use App\Services\ClientLoginLogServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientAuthenticatedSessionController extends Controller
{
    public function __construct(
        private ClientLoginLogServiceInterface $clientLoginLogService
    ) {
    }
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
            $this->clientLoginLogService->createLoginLogFromRequest($client, $request);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
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
