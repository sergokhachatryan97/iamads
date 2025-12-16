<?php

namespace App\Http\Controllers\Staff\Auth;

use App\Http\Controllers\Auth\BaseEmailVerificationNotificationController;

class StaffEmailVerificationNotificationController extends BaseEmailVerificationNotificationController
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
