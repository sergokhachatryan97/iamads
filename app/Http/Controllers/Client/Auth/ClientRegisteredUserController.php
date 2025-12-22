<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientLoginLogServiceInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class ClientRegisteredUserController extends Controller
{
    public function __construct(
        private ClientLoginLogServiceInterface $clientLoginLogService
    ) {
    }
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
        $this->clientLoginLogService->createLoginLogFromRequest($client, $request);

        return redirect(route('dashboard', absolute: false));
    }

}
