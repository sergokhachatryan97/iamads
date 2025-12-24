<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::guard('staff')->user();

        if (!$user) {
            abort(403, 'User does not have the right roles.');
        }

        // Check if user has any of the provided roles
        if (!$user->hasAnyRole($roles)) {
            abort(403, 'User does not have the right roles.');
        }

        return $next($request);
    }
}
