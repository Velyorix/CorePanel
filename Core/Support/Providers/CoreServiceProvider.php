<?php

namespace Core\Support\Providers;

use Core\Permissions\Services\PermissionService;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register Core services and bindings.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
    }

    /**
     * Bootstrap Core services after all providers are registered.
     */
    public function boot(): void
    {
        //
    }
}
