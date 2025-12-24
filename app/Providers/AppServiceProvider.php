<?php

namespace App\Providers;

use App\Repositories\CategoryRepository;
use App\Repositories\CategoryRepositoryInterface;
use App\Repositories\ClientLoginLogRepository;
use App\Repositories\ClientLoginLogRepositoryInterface;
use App\Repositories\ClientRepository;
use App\Repositories\ClientRepositoryInterface;
use App\Repositories\RoleRepository;
use App\Repositories\RoleRepositoryInterface;
use App\Repositories\ServiceRepository;
use App\Repositories\ServiceRepositoryInterface;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionPlanRepositoryInterface;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;
use App\Services\CategoryService;
use App\Services\CategoryServiceInterface;
use App\Services\ClientLoginLogService;
use App\Services\ClientLoginLogServiceInterface;
use App\Services\ClientService;
use App\Services\ClientServiceInterface;
use App\Services\RoleService;
use App\Services\RoleServiceInterface;
use App\Services\ServiceService;
use App\Services\ServiceServiceInterface;
use App\Services\SubscriptionPlanService;
use App\Services\SubscriptionPlanServiceInterface;
use App\Services\UserService;
use App\Services\UserServiceInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Yandex\Provider as YandexProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Repository interfaces to implementations
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(ClientLoginLogRepositoryInterface::class, ClientLoginLogRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(SubscriptionPlanRepositoryInterface::class, SubscriptionPlanRepository::class);

        // Bind Service interfaces to implementations
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(RoleServiceInterface::class, RoleService::class);
        $this->app->bind(ClientServiceInterface::class, ClientService::class);
        $this->app->bind(ClientLoginLogServiceInterface::class, ClientLoginLogService::class);
        $this->app->bind(CategoryServiceInterface::class, CategoryService::class);
        $this->app->bind(ServiceServiceInterface::class, ServiceService::class);
        $this->app->bind(SubscriptionPlanServiceInterface::class, SubscriptionPlanService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS when behind a proxy (e.g., ngrok)
        if (request()->server('HTTP_X_FORWARDED_PROTO') === 'https' || 
            request()->server('HTTPS') === 'on' ||
            (config('app.url') && str_starts_with(config('app.url'), 'https://'))) {
            URL::forceScheme('https');
        }
        
        // Set default pagination view to Tailwind
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.tailwind');
        \Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');

        // Register custom Socialite providers
        Event::listen(SocialiteWasCalled::class, function (SocialiteWasCalled $event) {
            $event->extendSocialite('yandex', YandexProvider::class);
            $event->extendSocialite('apple', AppleProvider::class);
        });

        // Super admin bypasses all permissions
        Gate::before(function ($user, $ability) {
            if (
                auth()->guard('staff')->check() &&
                $user->hasRole('super_admin')
            ) {
                return true;
            }

            return null;
        });
    }
}
