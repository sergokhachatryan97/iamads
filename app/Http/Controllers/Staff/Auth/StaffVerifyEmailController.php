<?php

namespace App\Http\Controllers\Staff\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffVerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        // Find the user by ID
        $user = User::find($id);
        
        if (!$user) {
            return redirect()->route('staff.login')
                ->withErrors(['verification' => 'Invalid verification link.']);
        }

        // Check if the email hash matches
        $expectedHash = sha1($user->getEmailForVerification());
        if (!hash_equals($expectedHash, $hash)) {
            return redirect()->route('staff.login')
                ->withErrors(['verification' => 'Invalid verification link.']);
        }

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            // If user is authenticated, redirect to dashboard, otherwise to login
            if (Auth::guard('staff')->check() && Auth::guard('staff')->id() === $user->id) {
                return redirect()->intended(route('staff.dashboard', absolute: false).'?verified=1');
            }
            return redirect()->route('staff.login')->with('status', 'email-already-verified');
        }

        // Verify the email
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // If user was not authenticated, log them in
        if (!Auth::guard('staff')->check()) {
            Auth::guard('staff')->login($user);
        }

        return redirect()->intended(route('staff.dashboard', absolute: false).'?verified=1');
    }
}
