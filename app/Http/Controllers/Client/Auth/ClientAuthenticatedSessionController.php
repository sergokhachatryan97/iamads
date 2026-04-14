<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Auth\ClientLoginRequest;
use App\Services\ClientLoginLogServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            return redirect()->route('login')
                ->withErrors($e->errors())
                ->withInput($request->only('email'));
        }

        // Get authenticated client
        $client = Auth::guard('client')->user();

        if ($client) {
            // Check if client is suspended
            if ($client->status === 'suspended') {
                Auth::guard('client')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your account is suspended. You cannot access the panel.'])
                    ->withInput($request->only('email'));
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
