<?php

namespace App\Http\Requests\Client\Auth;

use App\Http\Requests\Auth\BaseLoginRequest;

class ClientLoginRequest extends BaseLoginRequest
{
    /**
     * Get the guard name to use for authentication.
     */
    protected function guard(): string
    {
        return 'client';
    }

    /**
     * Get the throttle key prefix.
     */
    protected function throttleKeyPrefix(): string
    {
        return 'client';
    }
}
