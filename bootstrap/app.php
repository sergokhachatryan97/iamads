<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'staff.verified' => \App\Http\Middleware\EnsureStaffEmailIsVerified::class,
            'staff.role' => \App\Http\Middleware\EnsureStaffHasRole::class,
        ]);
        
        // Ensure UseStaffSession runs before StartSession
        // We prepend it to the web group so it runs early
        $middleware->web(prepend: [
            \App\Http\Middleware\UseStaffSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Redirect unauthenticated staff requests to staff login
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('staff/*')) {
                return redirect()->guest(route('staff.login'));
            }
            
            // For client routes, redirect to client login
            if ($request->routeIs('dashboard') || $request->routeIs('account.*') || $request->routeIs('balance.*')) {
                return redirect()->guest(route('login'));
            }
            
            // Default redirect (shouldn't normally reach here)
            return redirect()->guest(route('login'));
        });

    })->create();
