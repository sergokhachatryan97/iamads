<?php

use App\Jobs\SyncValidatingProviderOrdersJob;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
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
        //        $schedule->command('orders:sync-provider-status')
        //            ->daily()
        //            ->withoutOverlapping()
        //            ->runInBackground();

        //        $schedule->command('orders:process-validating-with-provider-sending')
        //            ->everyFiveMinutes()
        //            ->withoutOverlapping()
        //            ->runInBackground();

        //        $schedule->command('telegram:process-folder-expirations')
        //            ->everyFiveMinutes()
        //            ->withoutOverlapping()
        //            ->runInBackground();

        // Process due unsubscribe tasks every minute
        //        $schedule->job(new \App\Jobs\ProcessTelegramUnsubscribeTasksJob())
        //            ->everyMinute()
        //            ->withoutOverlapping();

        // Generate Telegram tasks for provider pull architecture
        //        $schedule->command('telegram:tasks:generate')
        //            ->everyMinute()
        //            ->withoutOverlapping()
        //            ->runInBackground();

        // Server health check: CPU, memory, disk, queue/Horizon.
        // Alerts via Telegram with per-metric cooldown (see config/health.php).
        $schedule->command('server:health-check')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->runInBackground();

        // Push-model pre-assignment: fills Redis service queues so /getOrder
        // requires only an LPOP + single-row UPDATE instead of a full DB transaction.
        // onOneServer() + withoutOverlapping() prevent concurrent instances.
        $schedule->job(new \App\Jobs\PreassignTelegramTasksJob(\App\Support\TelegramPremiumTemplateScope::SCOPE_DEFAULT))
            ->everyThirtySeconds()
            ->withoutOverlapping(1)
            ->onOneServer();

        $schedule->job(new \App\Jobs\PreassignTelegramTasksJob(\App\Support\TelegramPremiumTemplateScope::SCOPE_PREMIUM))
            ->everyThirtySeconds()
            ->withoutOverlapping(1)
            ->onOneServer();

        $schedule->command('socpanel:poll')
            ->everyThreeMinutes()
            ->withoutOverlapping(4);

//        $schedule->command('memberpro:poll')
//            ->everyThreeMinutes()
//            ->withoutOverlapping(4);

        $schedule->job(new SyncValidatingProviderOrdersJob('validating'))
            ->everyTenMinutes()
            ->withoutOverlapping(10)
            ->onOneServer();

//        $schedule->job(new SyncValidatingProviderOrdersJob('ok'))
//            ->everyFourHours()
//            ->withoutOverlapping(10)
//            ->onOneServer();

        $schedule->command('socpanel:cancel-invalid')
            ->everyMinute()
            ->withoutOverlapping(10);

        $schedule->job(new \App\Jobs\CleanExpiredYouTubeTasksJob)
            ->everyFiveMinutes()
            ->withoutOverlapping(2);

        $schedule->job(new \App\Jobs\CleanExpiredTelegramTasksJob)
            ->everyMinute()
            ->withoutOverlapping(2);

        $schedule->job(new \App\Jobs\CleanExpiredMaxTasksJob)
            ->everyMinute()
            ->withoutOverlapping(2);

        $schedule->command('max:clean-expired-subscriptions')
            ->daily()
            ->withoutOverlapping(10);

        $schedule->command('telegram:clean-expired-subscriptions')
            ->daily()
            ->withoutOverlapping(10);

        $schedule->job(new \App\Jobs\SyncCompletedProviderOrdersJob)
            ->everyFiveMinutes()
            ->withoutOverlapping(30)
            ->onOneServer();

        // Partial orders: fetch stats hourly for dashboard
        //        $schedule->job(new \App\Jobs\ProcessPartialOrdersJob())
        //            ->hourly()
        //            ->withoutOverlapping(15)
        //            ->onOneServer();

        // Sync Adtag provider services into local services table
        //        $schedule->job(new \App\Jobs\AdtagSyncServicesJob())
        //            ->hourly()
        //            ->withoutOverlapping(300);

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
            'throttle.provider_poll' => \App\Http\Middleware\ThrottleProviderPolling::class,
            'auth.external_client' => \App\Http\Middleware\AuthenticateExternalClient::class,
            'auth.api_client' => \App\Http\Middleware\AuthenticateApiClient::class,
            'auth.provider_api_key' => \App\Http\Middleware\AuthenticateProviderApiKey::class,
        ]);

        // Ensure UseStaffSession runs before StartSession
        // We prepend it to the web group so it runs early
        $middleware->web(prepend: [
            \App\Http\Middleware\UseStaffSession::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Trust proxies to properly detect HTTPS when behind a proxy (e.g., ngrok)
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // CSRF token mismatch: send users back to a normal page with a fresh token (not the 419 HTML view).
        // TokenMismatchException is converted to HttpException(419) by prepareException()
        // before render callbacks run, so we must catch HttpException and check the status code.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Page expired. Please refresh and try again.'),
                ], 419);
            }

            // After a POST, use 303 so “resubmit form” / refresh follows with GET (avoids repeat POST → 419 loops).
            $seeOtherIfPost = static function (RedirectResponse $response, Request $req): RedirectResponse {
                if ($req->isMethod('POST')) {
                    $response->setStatusCode(303);
                }

                return $response;
            };

            $expiredMessage = __('Your session expired. Please sign in again.');
            $routeName = $request->route()?->getName();

            if (in_array($routeName, ['staff.invitation.register', 'invitation.register'], true)) {
                $token = $request->route('token');
                if ($token) {
                    return $seeOtherIfPost(
                        redirect()->route('staff.invitation.accept', $token)
                            ->with('status', __('Your session expired. Please continue below.')),
                        $request
                    );
                }
            }

            if ($request->is('forgot-password') || $request->is('reset-password')) {
                return $seeOtherIfPost(redirect()->route('password.request')->with('status', $expiredMessage), $request);
            }

            if ($request->is('staff') || $request->is('staff/*')) {
                return $seeOtherIfPost(redirect()->guest(route('staff.login'))->with('status', $expiredMessage), $request);
            }

            if ($routeName && str_starts_with((string) $routeName, 'staff.')) {
                return $seeOtherIfPost(redirect()->guest(route('staff.login'))->with('status', $expiredMessage), $request);
            }

            if ($request->is('register')) {
                return $seeOtherIfPost(redirect()->route('register')->with('status', $expiredMessage), $request);
            }

            if ($request->is('login')) {
                return $seeOtherIfPost(redirect()->route('login')->with('status', $expiredMessage), $request);
            }

            if ($routeName === 'home.create-order') {
                return $seeOtherIfPost(
                    redirect()->route('home')->with('status', __('Your session expired. Please try again.')),
                    $request
                );
            }

            $path = $request->path();
            $clientPrefixes = [
                'dashboard',
                'account',
                'balance',
                'orders',
                'services',
                'subscriptions',
                'api',
                'logout',
                'order',
                'confirm-password',
                'password',
            ];
            foreach ($clientPrefixes as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return $seeOtherIfPost(redirect()->guest(route('login'))->with('status', $expiredMessage), $request);
                }
            }

            if ($routeName && (str_starts_with((string) $routeName, 'client.') || in_array($routeName, ['dashboard', 'logout', 'home.complete-order'], true))) {
                return $seeOtherIfPost(redirect()->guest(route('login'))->with('status', $expiredMessage), $request);
            }

            return $seeOtherIfPost(redirect()->guest(route('login'))->with('status', $expiredMessage), $request);
        });

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
