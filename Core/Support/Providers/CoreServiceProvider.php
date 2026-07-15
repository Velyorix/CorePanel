<?php

namespace Core\Support\Providers;

use App\Models\User;
use Core\Clients\Models\Client;
use Core\Permissions\Models\Role;
use Core\Permissions\Policies\ClientPolicy;
use Core\Permissions\Policies\RolePolicy;
use Core\Permissions\Policies\UserPolicy;
use Core\Permissions\Services\GateRegistrar;
use Core\Permissions\Services\PermissionRegistry;
use Core\Permissions\Services\PermissionService;
use Core\Permissions\Support\BladeAuthorizationDirectives;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register Core services and bindings.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(GateRegistrar::class);
    }

    /**
     * Bootstrap Core services after all providers are registered.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);

        $this->app->make(GateRegistrar::class)->register();
        BladeAuthorizationDirectives::register();
    }
}
