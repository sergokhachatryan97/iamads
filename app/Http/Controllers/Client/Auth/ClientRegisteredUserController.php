<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\AdminReferralService;
use App\Services\ClientLoginLogServiceInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use App\Models\Client as ClientModel;

class ClientRegisteredUserController extends Controller
{
    public function __construct(
        private ClientLoginLogServiceInterface $clientLoginLogService,
        private AdminReferralService $adminReferralService,
    ) {
    }
    /**
     * Display the client registration view.
     */
    public function create(Request $request): View
    {
        if ($request->has('ref')) {
            $request->session()->put('referral_code', $request->input('ref'));
        }

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

        // Client-to-client referral
        $referrerId = null;
        $refCode = $request->input('ref') ?? $request->session()->get('referral_code');
        if ($refCode) {
            $referrer = ClientModel::where('referral_code', $refCode)->first();
            if ($referrer) {
                $referrerId = $referrer->id;
            }
        }

        // Admin (staff) referral — auto-assign staff
        $staffId = $this->adminReferralService->resolveStaffId($request);

        $client = Client::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'staff_id' => $staffId,
            'referred_by_staff_id' => $staffId,
            'balance' => 0,
            'spent' => 0,
            'discount' => 0,
            'status' => 'active',
            'referred_by' => $referrerId,
        ]);

        if ($staffId) {
            $this->adminReferralService->clearReferral($request);
        }

        event(new Registered($client));

        Auth::guard('client')->login($client);

        // Update last_auth timestamp
        $client->update(['last_auth' => now()]);

        // Create login log entry for registration
        $this->clientLoginLogService->createLoginLogFromRequest($client, $request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

}
