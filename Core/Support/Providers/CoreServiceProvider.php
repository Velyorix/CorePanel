<?php

namespace Core\Support\Providers;

use App\Models\User;
use Core\Admin\Navigation\AdminNavigation;
use Core\Admin\Notifications\AdminNotificationFeed;
use Core\Client\Navigation\ClientNavigation;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientService;
use Core\License\Services\EntitlementService;
use Core\License\Services\LicenseSettings;
use Core\License\Services\CorePanelOrgClient;
use Core\License\Services\LicenseValidationService;
use Core\Nodes\Services\NodeGroupService;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Services\ProductCategoryService;
use Core\Products\Services\ProductPricingCalculator;
use Core\Products\Services\ProductService;
use Core\Permissions\Models\Role;
use Core\Permissions\Policies\ClientPolicy;
use Core\Permissions\Policies\ProductCategoryPolicy;
use Core\Permissions\Policies\ProductPolicy;
use Core\Permissions\Policies\RolePolicy;
use Core\Permissions\Policies\UserPolicy;
use Core\Permissions\Services\GateRegistrar;
use Core\Permissions\Services\PermissionRegistry;
use Core\Permissions\Services\PermissionService;
use Core\Permissions\Services\RoleInheritanceService;
use Core\Permissions\Services\RoleManagementService;
use Core\Permissions\Services\UserPermissionService;
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
        $this->app->singleton(RoleInheritanceService::class);
        $this->app->singleton(UserPermissionService::class);
        $this->app->singleton(RoleManagementService::class);
        $this->app->singleton(AdminNavigation::class);
        $this->app->singleton(ClientNavigation::class);
        $this->app->singleton(AdminNotificationFeed::class);
        $this->app->singleton(CorePanelOrgClient::class);
        $this->app->singleton(LicenseSettings::class);
        $this->app->singleton(LicenseValidationService::class);
        $this->app->singleton(EntitlementService::class);
        $this->app->singleton(ClientService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(ProductCategoryService::class);
        $this->app->singleton(ProductPricingCalculator::class);
        $this->app->singleton(NodeGroupService::class);
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
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);

        $this->app->make(GateRegistrar::class)->register();
        BladeAuthorizationDirectives::register();
    }
}
