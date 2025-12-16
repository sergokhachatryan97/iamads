<?php

namespace App\Http\Requests\Staff\Auth;

use App\Http\Requests\Auth\BaseLoginRequest;

class StaffLoginRequest extends BaseLoginRequest
{
    /**
     * Get the guard name to use for authentication.
     */
    protected function guard(): string
    {
        return 'staff';
    }

    /**
     * Get the throttle key prefix.
     */
    protected function throttleKeyPrefix(): string
    {
        return 'staff';
    }
}
