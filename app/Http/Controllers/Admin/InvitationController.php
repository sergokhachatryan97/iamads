<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * Display a listing of invitations.
     */
    public function index(): View
    {
        $invitations = Invitation::with('inviter')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('settings.invitations.index', [
            'invitations' => $invitations,
        ]);
    }

    /**
     * Show the form for creating a new invitation.
     */
    public function create(): View
    {
        $allowedRoles = ['admin', 'user'];
        
        return view('admin.invitations.create', [
            'allowedRoles' => $allowedRoles,
        ]);
    }

    /**
     * Store a newly created invitation.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:admin,user'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        // Check if user already exists
        if (User::where('email', $validated['email'])->exists()) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'A user with this email already exists.']);
        }

        // Check if there's already a valid invitation for this email
        $existingInvitation = Invitation::where('email', $validated['email'])
            ->valid()
            ->first();

        if ($existingInvitation) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'An active invitation already exists for this email.']);
        }

        // Generate secure token
        $token = Str::random(64);
        $tokenHash = Hash::make($token);

        // Create invitation
        $invitation = Invitation::create([
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token_hash' => $tokenHash,
            'name' => $validated['name'] ?? null,
            'expires_at' => now()->addHours(48),
            'invited_by' => $request->user()->id,
        ]);

        // Store token in session temporarily to show the link
        $request->session()->put('invitation_token_' . $invitation->id, $token);

        // Redirect to show invitation link
        return redirect()->route('settings.invitations.show', $invitation)
            ->with('token', $token);
    }

    /**
     * Show the invitation link for copying.
     */
    public function show(Invitation $invitation, Request $request): View|RedirectResponse
    {
        // Get token from session (stored temporarily) or flash data (from redirect)
        $token = $request->session()->get('invitation_token_' . $invitation->id) 
            ?? $request->session()->get('token');

        if (!$token) {
            return redirect()->route('settings.invitations.index')
                ->withErrors(['invitation' => 'Invitation token not found. Please create a new invitation.']);
        }

        // Store token back in session if it came from flash data
        if (!$request->session()->has('invitation_token_' . $invitation->id)) {
            $request->session()->put('invitation_token_' . $invitation->id, $token);
        }

        $invitationLink = route('invitation.accept', $token);

        return view('admin.invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
            'invitationLink' => $invitationLink,
        ]);
    }

    /**
     * Send the invitation email.
     */
    public function sendEmail(Invitation $invitation, Request $request): RedirectResponse
    {
        // Get token from session
        $token = $request->session()->get('invitation_token_' . $invitation->id);

        if (!$token) {
            return back()
                ->withErrors(['invitation' => 'Invitation token not found.']);
        }

        // Send invitation email
        Mail::to($invitation->email)->send(new InvitationMail($invitation, $token));

        // Clear token from session
        $request->session()->forget('invitation_token_' . $invitation->id);

        return back()->with('status', 'email-sent');
    }
}
