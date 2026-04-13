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
            return redirect()->route('staff.login')
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
            return redirect()->route('staff.login')
                ->withErrors(['invitation' => 'This invitation link is invalid or has expired.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Auto-verify: the invite token proves email ownership — only someone
        // with access to this email address could have received the invite link.
        $user = User::create([
            'name' => $validated['name'],
            'email' => $invitation->email,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        // Assign role from invitation
        $user->assignRole($invitation->role);

        // Mark invitation as accepted
        $invitation->update([
            'accepted_at' => now(),
        ]);

        // Log in the user using staff guard
        Auth::guard('staff')->login($user);

        return redirect()->route('staff.dashboard');
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
