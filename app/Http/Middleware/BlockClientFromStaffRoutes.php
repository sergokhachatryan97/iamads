<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BlockClientFromStaffRoutes
{
    /**
     * Handle an incoming request.
     * Blocks clients from accessing staff routes and redirects them to staff login.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If a client is authenticated, block access to staff routes
        if (Auth::guard('client')->check()) {
            // Redirect to staff login page (they'll be treated as guest for staff)
            return redirect()->route('staff.login')
                ->withErrors(['error' => 'You must log in as staff to access this area.']);
        }

        return $next($request);
    }
}
