<?php

namespace App\Http\Requests\Staff\Auth;

use Illuminate\Foundation\Auth\EmailVerificationRequest as BaseEmailVerificationRequest;
use Illuminate\Support\Facades\Auth;

class StaffEmailVerificationRequest extends BaseEmailVerificationRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Get user ID and hash from route parameters
        $userId = $this->route('id');
        $hash = $this->route('hash');
        
        if (!$userId || !$hash) {
            return false;
        }

        // Get user from route parameter (they might not be authenticated yet)
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            return false;
        }

        // Check if the email hash matches
        $expectedHash = sha1($user->getEmailForVerification());
        if (!hash_equals($expectedHash, (string) $hash)) {
            return false;
        }

        // If user is authenticated via staff guard, verify they match the route ID
        $authenticatedUser = Auth::guard('staff')->user();
        if ($authenticatedUser && (string) $authenticatedUser->getKey() !== (string) $userId) {
            return false;
        }

        return true;
    }

    /**
     * Get the user making the request.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user($guard = null)
    {
        // Return authenticated user if available, otherwise get user from route parameter
        $authenticatedUser = Auth::guard('staff')->user();
        if ($authenticatedUser && hash_equals((string) $authenticatedUser->getKey(), (string) $this->route('id'))) {
            return $authenticatedUser;
        }
        
        // If not authenticated or IDs don't match, get user from route
        return \App\Models\User::find($this->route('id'));
    }
}
