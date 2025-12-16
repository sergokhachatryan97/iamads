<?php

namespace App\Http\Controllers\Staff\Auth;

use App\Http\Controllers\Auth\BaseEmailVerificationPromptController;

class StaffEmailVerificationPromptController extends BaseEmailVerificationPromptController
{
    /**
     * Get the guard name to use.
     */
    protected function guard(): string
    {
        return 'staff';
    }

    /**
     * Get the dashboard route name.
     */
    protected function dashboardRoute(): string
    {
        return 'staff.dashboard';
    }
}
