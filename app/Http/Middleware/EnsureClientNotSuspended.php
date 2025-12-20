<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientNotSuspended
{
    /**
     * Handle an incoming request.
     * Blocks suspended clients from accessing client panel routes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('client')->check()) {
            $client = Auth::guard('client')->user();
            
            if ($client && $client->status === 'suspended') {
                Auth::guard('client')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your account is suspended. You cannot access the panel.']);
            }
        }

        return $next($request);
    }
}
