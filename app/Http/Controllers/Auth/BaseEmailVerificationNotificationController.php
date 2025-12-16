<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

abstract class BaseEmailVerificationNotificationController extends Controller
{
    /**
     * Get the guard name to use.
     */
    abstract protected function guard(): string;

    /**
     * Get the dashboard route name.
     */
    abstract protected function dashboardRoute(): string;

    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::guard($this->guard())->user();
        
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route($this->dashboardRoute(), absolute: false));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}

