<?php

namespace App\Http\Controllers\Staff\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Auth\StaffLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StaffAuthenticatedSessionController extends Controller
{
    /**
     * Display the staff login view.
     */
    public function create(): View|RedirectResponse
    {
        // If a client is logged in, redirect them away
        if (Auth::guard('client')->check()) {
            return redirect()->route('dashboard')
                ->withErrors(['error' => 'You are logged in as a client. Please log out first to access staff area.']);
        }

        return view('staff.auth.login');
    }

    /**
     * Handle an incoming staff authentication request.
     */
    public function store(StaffLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('staff.dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated staff session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }
}
