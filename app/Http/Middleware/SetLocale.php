<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request. Set app locale from session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        if ($locale && array_key_exists($locale, config('locales', []))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
