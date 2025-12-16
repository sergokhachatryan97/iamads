<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseStaffSession
{
    /**
     * Handle an incoming request.
     * This middleware must run BEFORE StartSession middleware
     * to configure the session cookie name and path for staff routes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only configure session for /staff/* routes
        if ($request->is('staff*')) {
            // Configure session cookie for staff routes
            // This must be set before StartSession middleware runs
            config(['session.cookie' => 'staff_session']);
            config(['session.path' => '/staff']);
        }

        return $next($request);
    }
}
