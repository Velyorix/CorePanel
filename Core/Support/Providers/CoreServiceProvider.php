<?php

namespace Core\Support\Providers;

use App\Models\User;
use Core\Admin\Navigation\AdminNavigation;
use Core\Admin\Notifications\AdminNotificationFeed;
use Core\Client\Navigation\ClientNavigation;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientService;
use Core\Billing\Contracts\OverdueServiceActions;
use Core\Billing\Contracts\RenewableBillableSource;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Services\BillingSettings;
use Core\Billing\Services\InvoiceGenerationService;
use Core\Billing\Services\InvoiceNumberService;
use Core\Billing\Services\InvoiceReminderService;
use Core\Billing\Services\NullOverdueServiceActions;
use Core\Billing\Services\NullRenewableBillableSource;
use Core\Billing\Services\ClientCreditService;
use Core\Billing\Services\CouponService;
use Core\Billing\Services\DiscountCalculator;
use Core\Billing\Services\OverdueSuspensionService;
use Core\Billing\Services\ProrataCalculationService;
use Core\Billing\Services\PaymentGatewayRegistry;
use Core\Billing\Services\PaymentService;
use Core\Billing\Services\QuoteNumberService;
use Core\Billing\Services\QuoteService;
use Core\Billing\Services\RenewalInvoiceService;
use Core\Billing\Services\TaxCalculationService;
use Core\License\Services\EntitlementService;
use Core\License\Services\LicenseSettings;
use Core\License\Services\CorePanelOrgClient;
use Core\License\Services\LicenseValidationService;
use Core\Nodes\Services\NodeGroupService;
use Core\Orders\Models\Order;
use Core\Orders\Services\CartService;
use Core\Orders\Services\CartSummary;
use Core\Orders\Services\CheckoutDraftService;
use Core\Orders\Services\OrderConversionService;
use Core\Orders\Services\OrderService;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Services\CatalogPricePreview;
use Core\Products\Services\CatalogService;
use Core\Products\Services\ConfiguratorService;
use Core\Products\Services\ProductCategoryService;
use Core\Products\Services\ProductPricingCalculator;
use Core\Products\Services\ProductService;
use Core\Permissions\Models\Role;
use Core\Permissions\Policies\ClientPolicy;
use Core\Permissions\Policies\OrderPolicy;
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
        $this->app->singleton(CatalogService::class);
        $this->app->singleton(ConfiguratorService::class);
        $this->app->singleton(CatalogPricePreview::class);
        $this->app->singleton(ProductPricingCalculator::class);
        $this->app->singleton(NodeGroupService::class);
        $this->app->singleton(CartService::class);
        $this->app->singleton(TaxCalculationService::class);
        $this->app->singleton(CartSummary::class);
        $this->app->singleton(CheckoutDraftService::class);
        $this->app->singleton(OrderConversionService::class);
        $this->app->singleton(OrderService::class);
        $this->app->singleton(InvoiceGenerationService::class);
        $this->app->singleton(BillingSettings::class);
        $this->app->singleton(InvoiceNumberService::class);
        $this->app->singleton(RenewableBillableSource::class, NullRenewableBillableSource::class);
        $this->app->singleton(RenewalInvoiceService::class);
        $this->app->singleton(PaymentGatewayRegistry::class);
        $this->app->singleton(ManualTransferGateway::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(QuoteNumberService::class);
        $this->app->singleton(QuoteService::class);
        $this->app->singleton(ClientCreditService::class);
        $this->app->singleton(DiscountCalculator::class);
        $this->app->singleton(CouponService::class);
        $this->app->singleton(ProrataCalculationService::class);
        $this->app->singleton(InvoiceReminderService::class);
        $this->app->singleton(OverdueServiceActions::class, NullOverdueServiceActions::class);
        $this->app->singleton(OverdueSuspensionService::class);
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
        Gate::policy(Order::class, OrderPolicy::class);

        $this->app->make(GateRegistrar::class)->register();
        BladeAuthorizationDirectives::register();

        if ((bool) config('corepanel.billing.manual_transfer.enabled', true)) {
            $this->app->make(PaymentGatewayRegistry::class)->register(
                $this->app->make(ManualTransferGateway::class),
            );
        }
    }
}
