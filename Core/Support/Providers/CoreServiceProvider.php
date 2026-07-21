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
use Core\Billing\Services\BillingAuditLogger;
use Core\Billing\Services\BillingSettings;
use Core\Billing\Services\InvoiceGenerationService;
use Core\Billing\Services\InvoiceNumberService;
use Core\Billing\Services\InvoiceReminderService;
use Core\Billing\Services\InvoiceService;
use Core\Billing\Services\LifecycleOverdueServiceActions;
use Core\Billing\Services\NullRenewableBillableSource;
use Core\Billing\Services\ClientCreditService;
use Core\Billing\Services\CouponService;
use Core\Billing\Services\CreditNoteNumberService;
use Core\Billing\Services\CreditNoteService;
use Core\Billing\Services\BillingDocumentPdfService;
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
use Core\Nodes\Services\NodeAllocationAlgorithm;
use Core\Nodes\Services\NodeLoadBalancingService;
use Core\Nodes\Services\NodeCapacityService;
use Core\Nodes\Services\NodeClusterService;
use Core\Nodes\Services\NodeConnectionTestService;
use Core\Nodes\Services\NodeCredentialsService;
use Core\Nodes\Services\NodeFailoverService;
use Core\Nodes\Services\NodeGroupService;
use Core\Nodes\Services\NodeHealthCheckService;
use Core\Nodes\Services\NodeLogService;
use Core\Nodes\Services\NodeMetricsCollectionService;
use Core\Nodes\Services\NodeMonitoringService;
use Core\Nodes\Services\NodeTelemetryService;
use Core\Nodes\Services\NodeService;
use Core\Nodes\Services\NodeSshClient;
use Core\Nodes\Services\NodeSshKnownHostsStore;
use Core\Nodes\Services\NodeSshProbeService;
use Core\Nodes\Models\Node;
use Core\Orders\Models\Order;
use Core\Orders\Services\CartService;
use Core\Orders\Services\CartSummary;
use Core\Orders\Services\CheckoutDraftService;
use Core\Orders\Services\OrderConversionService;
use Core\Orders\Services\OrderService;
use Core\Orders\Events\OrderPaid;
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
use Core\Permissions\Policies\InvoicePolicy;
use Core\Nodes\Models\NodeCluster;
use Core\Nodes\Models\NodeGroup;
use Core\Permissions\Policies\NodeClusterPolicy;
use Core\Permissions\Policies\NodeGroupPolicy;
use Core\Permissions\Policies\NodePolicy;
use Core\Permissions\Policies\OrderPolicy;
use Core\Permissions\Policies\PaymentPolicy;
use Core\Permissions\Policies\ProductCategoryPolicy;
use Core\Permissions\Policies\ProductPolicy;
use Core\Permissions\Policies\QuotePolicy;
use Core\Permissions\Policies\RolePolicy;
use Core\Permissions\Policies\ServicePolicy;
use Core\Permissions\Policies\UserPolicy;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Payment;
use Core\Billing\Models\Quote;
use Core\Services\Models\Service;
use Core\Permissions\Services\GateRegistrar;
use Core\Permissions\Services\PermissionRegistry;
use Core\Permissions\Services\PermissionService;
use Core\Permissions\Services\RoleInheritanceService;
use Core\Permissions\Services\RoleManagementService;
use Core\Permissions\Services\UserPermissionService;
use Core\Permissions\Support\BladeAuthorizationDirectives;
use Core\Providers\Services\ModulePermissionRegistrar;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Provisioning\Services\NodeSelectionService;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Provisioning\Services\ProvisioningDeadLetterService;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Provisioning\Services\ProvisioningRollbackService;
use Core\Services\Contracts\ModuleAccessLinkProvider;
use Core\Services\Contracts\ModuleActionDispatcher;
use Core\Services\Contracts\ServiceActionLogger;
use Core\Services\Listeners\CreateServicesOnOrderPaid;
use Core\Services\Services\DatabaseServiceActionLogger;
use Core\Services\Services\NullModuleAccessLinkProvider;
use Core\Services\Services\NullModuleActionDispatcher;
use Core\Services\Services\ServiceAccessService;
use Core\Services\Services\ServiceActionLogService;
use Core\Services\Services\ServiceConfigService;
use Core\Services\Services\ServiceControlService;
use Core\Services\Services\ServiceCreationService;
use Core\Services\Services\ServiceLifecycleService;
use Core\Services\Services\ServiceQueryService;
use Core\Services\Services\ServiceUpgradeService;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(NodeClusterService::class);
        $this->app->singleton(NodeCredentialsService::class);
        $this->app->singleton(NodeAllocationAlgorithm::class);
        $this->app->singleton(NodeLoadBalancingService::class);
        $this->app->singleton(NodeCapacityService::class);
        $this->app->singleton(NodeConnectionTestService::class);
        $this->app->singleton(NodeSshKnownHostsStore::class);
        $this->app->singleton(NodeSshClient::class);
        $this->app->singleton(NodeSshProbeService::class);
        $this->app->singleton(NodeMetricsCollectionService::class);
        $this->app->singleton(NodeHealthCheckService::class);
        $this->app->singleton(NodeMonitoringService::class);
        $this->app->singleton(NodeTelemetryService::class);
        $this->app->singleton(NodeFailoverService::class);
        $this->app->singleton(NodeLogService::class);
        $this->app->singleton(NodeService::class);
        $this->app->singleton(CartService::class);
        $this->app->singleton(TaxCalculationService::class);
        $this->app->singleton(CartSummary::class);
        $this->app->singleton(CheckoutDraftService::class);
        $this->app->singleton(OrderConversionService::class);
        $this->app->singleton(OrderService::class);
        $this->app->singleton(ServiceLifecycleService::class);
        $this->app->singleton(ServiceCreationService::class);
        $this->app->singleton(ProviderRegistry::class);
        $this->app->singleton(ModulePermissionRegistrar::class);
        $this->app->singleton(ProviderResourceMappingService::class);
        $this->app->singleton(ProvisioningRollbackService::class);
        $this->app->singleton(NodeSelectionService::class);
        $this->app->singleton(ProvisioningEngine::class);
        $this->app->singleton(ProvisioningDeadLetterService::class);
        $this->app->singleton(ModuleActionDispatcher::class, NullModuleActionDispatcher::class);
        $this->app->singleton(ModuleAccessLinkProvider::class, NullModuleAccessLinkProvider::class);
        $this->app->singleton(ServiceActionLogger::class, DatabaseServiceActionLogger::class);
        $this->app->singleton(ServiceControlService::class);
        $this->app->singleton(ServiceUpgradeService::class);
        $this->app->singleton(ServiceConfigService::class);
        $this->app->singleton(ServiceAccessService::class);
        $this->app->singleton(ServiceActionLogService::class);
        $this->app->singleton(ServiceQueryService::class);
        $this->app->singleton(InvoiceGenerationService::class);
        $this->app->singleton(BillingSettings::class);
        $this->app->singleton(BillingAuditLogger::class);
        $this->app->singleton(InvoiceNumberService::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(RenewableBillableSource::class, NullRenewableBillableSource::class);
        $this->app->singleton(RenewalInvoiceService::class);
        $this->app->singleton(PaymentGatewayRegistry::class);
        $this->app->singleton(ManualTransferGateway::class);
        $this->app->singleton(StubServerProvider::class);
        $this->app->singleton(StubNodeProvider::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(QuoteNumberService::class);
        $this->app->singleton(QuoteService::class);
        $this->app->singleton(ClientCreditService::class);
        $this->app->singleton(DiscountCalculator::class);
        $this->app->singleton(CouponService::class);
        $this->app->singleton(ProrataCalculationService::class);
        $this->app->singleton(InvoiceReminderService::class);
        $this->app->singleton(OverdueServiceActions::class, LifecycleOverdueServiceActions::class);
        $this->app->singleton(OverdueSuspensionService::class);
        $this->app->singleton(CreditNoteNumberService::class);
        $this->app->singleton(CreditNoteService::class);
        $this->app->singleton(BillingDocumentPdfService::class);
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
        Gate::policy(Node::class, NodePolicy::class);
        Gate::policy(NodeGroup::class, NodeGroupPolicy::class);
        Gate::policy(NodeCluster::class, NodeClusterPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);

        $this->app->make(GateRegistrar::class)->register();
        BladeAuthorizationDirectives::register();

        if ((bool) config('corepanel.billing.manual_transfer.enabled', true)) {
            $this->app->make(PaymentGatewayRegistry::class)->register(
                $this->app->make(ManualTransferGateway::class),
            );
        }

        if ((bool) config('corepanel.provisioning.stub.enabled', false)) {
            $registry = $this->app->make(ProviderRegistry::class);
            $registry->registerServer($this->app->make(StubServerProvider::class));

            if ((bool) config('corepanel.provisioning.stub.register_node_provider', true)) {
                $registry->registerNode($this->app->make(StubNodeProvider::class));
            }
        }

        Event::listen(OrderPaid::class, CreateServicesOnOrderPaid::class);
    }
}
