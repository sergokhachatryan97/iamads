<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class AcceptInvitationController extends Controller
{
    /**
     * Show the registration form for accepting an invitation.
     */
    public function show(string $token): View|RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        if (!$invitation) {
            return redirect()->route('login')
                ->withErrors(['invitation' => 'This invitation link is invalid or has expired.']);
        }

        return view('auth.invite-register', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    /**
     * Register a new user via invitation.
     */
    public function register(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        if (!$invitation) {
            return redirect()->route('login')
                ->withErrors(['invitation' => 'This invitation link is invalid or has expired.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Create user with form data (email_verified_at will be null - user must verify)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $invitation->email,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => null, // User must verify email
        ]);

        // Assign role from invitation
        $user->assignRole($invitation->role);

        // Mark invitation as accepted
        $invitation->update([
            'accepted_at' => now(),
        ]);

        event(new Registered($user));

        // Log in the user
        Auth::login($user);

        // Redirect to email verification notice (user must verify before accessing anything)
        return redirect()->route('verification.notice');
    }

    /**
     * Find a valid invitation by token.
     */
    private function findValidInvitation(string $token): ?Invitation
    {
        $invitations = Invitation::whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($invitations as $invitation) {
            if (Hash::check($token, $invitation->token_hash)) {
                return $invitation;
            }
        }

        return null;
    }
}
