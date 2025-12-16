<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

abstract class BaseEmailVerificationPromptController extends Controller
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
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = Auth::guard($this->guard())->user();
        
        return $user->hasVerifiedEmail()
                    ? redirect()->intended(route($this->dashboardRoute(), absolute: false))
                    : view('auth.verify-email');
    }
}

