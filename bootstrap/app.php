<?php

use App\Jobs\SyncValidatingProviderOrdersJob;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
//        $schedule->command('subscriptions:renew-expired')
//            ->daily()
//            ->at('00:00')
//            ->timezone(config('app.timezone'));
//
//        // Poll provider status every 10 minutes as fallback to webhooks
        $schedule->command('orders:sync-provider-status')
            ->daily()
            ->withoutOverlapping()
            ->runInBackground();

        // Process due unsubscribe tasks every minute
//        $schedule->job(new \App\Jobs\ProcessTelegramUnsubscribeTasksJob())
//            ->everyMinute()
//            ->withoutOverlapping();

        // Generate Telegram tasks for provider pull architecture
//        $schedule->command('telegram:tasks:generate')
//            ->everyMinute()
//            ->withoutOverlapping()
//            ->runInBackground();


        $schedule->command('socpanel:poll')
            ->everyThreeMinutes()
            ->withoutOverlapping(4);

//        $schedule->job(new SyncValidatingProviderOrdersJob())
//            ->everyMinute()
//            ->withoutOverlapping(10)
//            ->onOneServer();
//
        $schedule->command('socpanel:cancel-invalid')
            ->everyMinute()
            ->withoutOverlapping(10);

        $schedule->job(new \App\Jobs\SyncCompletedProviderOrdersJob())
            ->everyFiveMinutes()
            ->withoutOverlapping(30)
            ->onOneServer();

        // Partial orders: fetch stats hourly for dashboard
        $schedule->job(new \App\Jobs\ProcessPartialOrdersJob())
            ->hourly()
            ->withoutOverlapping(15)
            ->onOneServer();

        // Sync Adtag provider services into local services table
//        $schedule->job(new \App\Jobs\AdtagSyncServicesJob())
//            ->hourly()
//            ->withoutOverlapping(300);

        // Cancel invalid Socpanel orders (invalid_link/restricted) on provider via editOrder
//        $schedule->job(new \App\Jobs\SocpanelCancelInvalidOrderJob()
//            ->everyFiveMinutes()
//            ->withoutOverlapping(120);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'staff.verified' => \App\Http\Middleware\EnsureStaffEmailIsVerified::class,
            'staff.role' => \App\Http\Middleware\EnsureStaffHasRole::class,
            'client.status' => \App\Http\Middleware\EnsureClientNotSuspended::class,
            'auth.provider' => \App\Http\Middleware\AuthenticateProvider::class,
        ]);

        // Ensure UseStaffSession runs before StartSession
        // We prepend it to the web group so it runs early
        $middleware->web(prepend: [
            \App\Http\Middleware\UseStaffSession::class,
        ]);

        // Trust proxies to properly detect HTTPS when behind a proxy (e.g., ngrok)
        $middleware->trustProxies(at: '*');
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
