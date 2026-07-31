<?php

namespace Core\Support\Providers;

use App\Models\User;
use Core\Admin\Navigation\AdminNavigation;
use Core\Admin\Notifications\AdminNotificationFeed;
use Core\Admin\Services\AdminNotificationService;
use Core\API\Services\ApiClientAccessService;
use Core\API\Services\ApiRateLimiter;
use Core\API\Services\ApiRequestLogger;
use Core\API\Services\ApiScopeRegistry;
use Core\API\Services\ApiTokenScopeChecker;
use Core\API\Services\ApiTokenService;
use Core\API\Services\InternalApiAuthenticator;
use Core\Automation\Services\AutomationEventBridge;
use Core\Automation\Services\AutomationEventBus;
use Core\Automation\Services\AutomationEventPayloadFactory;
use Core\Automation\Services\WorkflowActionRegistry;
use Core\Automation\Services\WorkflowConditionEvaluator;
use Core\Automation\Services\WorkflowEngine;
use Core\Webhooks\Listeners\DispatchOutgoingWebhooks;
use Core\Webhooks\Services\WebhookDeliveryService;
use Core\Webhooks\Services\WebhookDispatcher;
use Core\Webhooks\Services\WebhookService;
use Core\Notifications\Services\NotificationPreferenceService;
use Core\Notifications\Services\NotificationService;
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
use Core\Billing\Services\GatewayManager;
use Core\Billing\Services\PaymentGatewayInjector;
use Core\Billing\Services\PaymentService;
use Core\Billing\Services\QuoteNumberService;
use Core\Billing\Services\QuoteService;
use Core\Billing\Services\RenewalInvoiceService;
use Core\Billing\Services\TaxCalculationService;
use Core\License\Services\EntitlementService;
use Core\License\Services\LicenseSettings;
use Core\Settings\Services\SettingsService;
use Core\License\Services\CorePanelOrgClient;
use Core\License\Services\LicenseValidationService;
use Core\Marketplace\Services\MarketplaceCatalogCache;
use Core\Marketplace\Services\MarketplaceClient;
use Core\Marketplace\Services\MarketplaceCompatibilityGuard;
use Core\Marketplace\Services\MarketplaceEntitlementGuard;
use Core\Marketplace\Services\MarketplaceInstaller;
use Core\Marketplace\Services\MarketplacePackageDownloader;
use Core\Marketplace\Services\MarketplacePackageExtractor;
use Core\Marketplace\Services\MarketplacePackageIntegrityGuard;
use Core\Marketplace\Services\MarketplacePackageOriginStore;
use Core\Marketplace\Services\MarketplaceUpdateChecker;
use Core\Nodes\Services\NodeAllocationAlgorithm;
use Core\Nodes\Services\NodeLoadBalancingService;
use Core\Nodes\Services\NodeOverloadService;
use Core\Nodes\Services\NodeAuditLogger;
use Core\Nodes\Services\NodeConnectionSecurityService;
use Core\Nodes\Services\NodeHttpClient;
use Core\Nodes\Services\NodeIpWhitelistService;
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
use Core\Permissions\Policies\KbArticlePolicy;
use Core\Permissions\Policies\KbCategoryPolicy;
use Core\Permissions\Policies\TicketPolicy;
use Core\KnowledgeBase\Models\KbArticle;
use Core\KnowledgeBase\Models\KbCategory;
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
use Core\Tickets\Models\Ticket;
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
use Core\Modules\Services\ModuleDatabaseGuard;
use Core\Modules\Services\ModuleCapabilityValidator;
use Core\Modules\Services\ModuleHookRegistry;
use Core\Modules\Console\ModuleListCommand;
use Core\Modules\Console\ModuleMakeCommand;
use Core\Modules\Console\ModuleMakeControllerCommand;
use Core\Modules\Console\ModuleMakeEventCommand;
use Core\Modules\Console\ModuleMakeFactoryCommand;
use Core\Modules\Console\ModuleMakeGatewayCommand;
use Core\Modules\Console\ModuleMakeListenerCommand;
use Core\Modules\Console\ModuleMakeMigrationCommand;
use Core\Modules\Console\ModuleMakeModelCommand;
use Core\Modules\Console\ModuleMakeModuleCommandCommand;
use Core\Modules\Console\ModuleMakePolicyCommand;
use Core\Modules\Console\ModuleMakeProviderCommand;
use Core\Modules\Console\ModuleMakeRequestCommand;
use Core\Modules\Console\ModuleMakeSeederCommand;
use Core\Modules\Console\ModuleMakeServiceCommand;
use Core\Modules\Console\ModuleMakeTraitCommand;
use Core\Modules\Services\ModuleEventBridge;
use Core\Modules\Services\ModuleGenerator;
use Core\Modules\Services\ModuleScaffolder;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleManager;
use Core\Themes\Console\ThemeBuildCommand;
use Core\Themes\Console\ThemeMakeAssetCommand;
use Core\Themes\Console\ThemeMakeCommand;
use Core\Themes\Console\ThemeMakeComponentCommand;
use Core\Themes\Console\ThemeMakeLayoutCommand;
use Core\Themes\Console\ThemeMakePartialCommand;
use Core\Themes\Console\ThemeMakeViewCommand;
use Core\Themes\Console\ThemeWatchCommand;
use Core\Themes\Services\ThemeGenerator;
use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeScaffolder;
use Core\Themes\Services\ThemeViewRegistrar;
use Core\Themes\Services\ThemeViteBuilder;
use Core\Themes\Services\ThemeViteEntryResolver;
use Core\Themes\Services\ThemeStateRepository;
use Core\Modules\Services\ModulePackageHasher;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Core\Modules\Services\ModuleSignatureVerifier;
use Core\Modules\Services\ModuleStateRepository;
use Core\Modules\Services\ModuleTableAccessPolicy;
use Core\Sync\Services\NodeSyncService;
use Core\Sync\Services\ServiceSyncComparisonService;
use Core\Sync\Services\ServiceSyncResolutionService;
use Core\Sync\Services\ServiceSyncService;
use Core\Sync\Services\SyncAlertService;
use Core\Sync\Services\SyncLogQueryService;
use Core\Sync\Services\SyncLogService;
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
use Core\Tickets\Events\TicketCreated;
use Core\Tickets\Events\TicketReplied;
use Core\Tickets\Listeners\NotifyParticipantsOnTicketReplied;
use Core\Tickets\Listeners\NotifyStaffOnTicketCreated;
use Core\Tickets\Services\TicketAttachmentService;
use Core\Tickets\Services\TicketNotificationService;
use Core\Tickets\Services\TicketNumberService;
use Core\Tickets\Services\TicketRateLimiter;
use Core\Tickets\Services\TicketService;
use Core\KnowledgeBase\Services\KbCategoryService;
use Core\KnowledgeBase\Services\KnowledgeBaseService;
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
        $this->app->singleton(AdminNotificationService::class);
        $this->app->singleton(ApiScopeRegistry::class);
        $this->app->singleton(ApiTokenScopeChecker::class);
        $this->app->singleton(ApiTokenService::class);
        $this->app->singleton(ApiRateLimiter::class);
        $this->app->singleton(ApiClientAccessService::class);
        $this->app->singleton(ApiRequestLogger::class);
        $this->app->singleton(InternalApiAuthenticator::class);
        $this->app->singleton(AutomationEventBus::class);
        $this->app->singleton(AutomationEventPayloadFactory::class);
        $this->app->singleton(AutomationEventBridge::class);
        $this->app->singleton(WorkflowConditionEvaluator::class);
        $this->app->singleton(WorkflowActionRegistry::class, function (): WorkflowActionRegistry {
            $registry = new WorkflowActionRegistry;
            $registry->registerDefaults();

            return $registry;
        });
        $this->app->singleton(WorkflowEngine::class);
        $this->app->singleton(WebhookService::class);
        $this->app->singleton(WebhookDispatcher::class);
        $this->app->singleton(WebhookDeliveryService::class);
        $this->app->singleton(NotificationPreferenceService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(AdminNotificationFeed::class);
        $this->app->singleton(ModuleStateRepository::class);
        $this->app->singleton(ModuleSandbox::class);
        $this->app->singleton(ModuleHookRegistry::class);
        $this->app->singleton(ModuleCapabilityValidator::class);
        $this->app->singleton(ModuleEventBridge::class);
        $this->app->singleton(ModuleTableAccessPolicy::class);
        $this->app->singleton(ModuleDatabaseGuard::class);
        $this->app->singleton(ModulePackageHasher::class);
        $this->app->singleton(ModuleSignatureVerifier::class);
        $this->app->singleton(InstalledModuleRepository::class);
        $this->app->singleton(ModuleServiceProviderRegistrar::class);
        $this->app->singleton(ModuleResourceLoader::class);
        $this->app->singleton(ModuleFactory::class);
        $this->app->singleton(ModuleRequirementChecker::class);
        $this->app->singleton(ModuleManager::class);
        $this->app->singleton(ModuleScaffolder::class);
        $this->app->singleton(ModuleGenerator::class);
        $this->app->singleton(ThemeStateRepository::class);
        $this->app->singleton(ThemeViewRegistrar::class);
        $this->app->singleton(ThemeViteEntryResolver::class);
        $this->app->singleton(ThemeViteBuilder::class);
        $this->app->singleton(ThemeScaffolder::class);
        $this->app->singleton(ThemeGenerator::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(CorePanelOrgClient::class);
        $this->app->singleton(MarketplaceCatalogCache::class);
        $this->app->singleton(MarketplaceClient::class);
        $this->app->singleton(MarketplaceEntitlementGuard::class);
        $this->app->singleton(MarketplaceCompatibilityGuard::class);
        $this->app->singleton(MarketplacePackageDownloader::class);
        $this->app->singleton(MarketplacePackageExtractor::class);
        $this->app->singleton(MarketplacePackageIntegrityGuard::class);
        $this->app->singleton(MarketplacePackageOriginStore::class);
        $this->app->singleton(MarketplaceInstaller::class);
        $this->app->singleton(MarketplaceUpdateChecker::class);

        $this->commands([
            ThemeMakeCommand::class,
            ThemeMakeViewCommand::class,
            ThemeMakeLayoutCommand::class,
            ThemeMakeComponentCommand::class,
            ThemeMakePartialCommand::class,
            ThemeMakeAssetCommand::class,
            ThemeBuildCommand::class,
            ThemeWatchCommand::class,
            ModuleMakeCommand::class,
            ModuleMakeMigrationCommand::class,
            ModuleMakeModelCommand::class,
            ModuleMakeControllerCommand::class,
            ModuleMakeServiceCommand::class,
            ModuleMakeEventCommand::class,
            ModuleMakeFactoryCommand::class,
            ModuleMakeSeederCommand::class,
            ModuleMakeListenerCommand::class,
            ModuleMakeTraitCommand::class,
            ModuleMakeProviderCommand::class,
            ModuleMakeGatewayCommand::class,
            ModuleMakeModuleCommandCommand::class,
            ModuleMakeRequestCommand::class,
            ModuleMakePolicyCommand::class,
            ModuleListCommand::class,
        ]);
        $this->app->singleton(LicenseSettings::class);
        $this->app->singleton(SettingsService::class);
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
        $this->app->singleton(NodeOverloadService::class);
        $this->app->singleton(NodeHttpClient::class);
        $this->app->singleton(NodeIpWhitelistService::class);
        $this->app->singleton(NodeConnectionSecurityService::class);
        $this->app->singleton(NodeAuditLogger::class);
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
        $this->app->singleton(TicketNumberService::class);
        $this->app->singleton(TicketAttachmentService::class);
        $this->app->singleton(TicketRateLimiter::class);
        $this->app->singleton(TicketNotificationService::class);
        $this->app->singleton(TicketService::class);
        $this->app->singleton(KbCategoryService::class);
        $this->app->singleton(KnowledgeBaseService::class);
        $this->app->singleton(ProviderRegistry::class);
        $this->app->singleton(ModulePermissionRegistrar::class);
        $this->app->singleton(ProviderResourceMappingService::class);
        $this->app->singleton(ProvisioningRollbackService::class);
        $this->app->singleton(NodeSelectionService::class);
        $this->app->singleton(ProvisioningEngine::class);
        $this->app->singleton(ServiceSyncComparisonService::class);
        $this->app->singleton(ServiceSyncResolutionService::class);
        $this->app->singleton(SyncLogService::class);
        $this->app->singleton(SyncLogQueryService::class);
        $this->app->singleton(SyncAlertService::class);
        $this->app->singleton(ServiceSyncService::class);
        $this->app->singleton(NodeSyncService::class);
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
        $this->app->singleton(GatewayManager::class);
        $this->app->singleton(PaymentGatewayInjector::class);
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
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(KbArticle::class, KbArticlePolicy::class);
        Gate::policy(KbCategory::class, KbCategoryPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);

        $this->app->make(GateRegistrar::class)->register();
        BladeAuthorizationDirectives::register();

        $gateways = $this->app->make(GatewayManager::class);
        $manualGateway = $this->app->make(ManualTransferGateway::class);
        $gateways->register($manualGateway);
        $this->app->make(ProviderRegistry::class)->registerPaymentGateway($manualGateway);

        if ((bool) config('corepanel.provisioning.stub.enabled', false)) {
            $registry = $this->app->make(ProviderRegistry::class);
            $registry->registerServer($this->app->make(StubServerProvider::class));

            if ((bool) config('corepanel.provisioning.stub.register_node_provider', true)) {
                $registry->registerNode($this->app->make(StubNodeProvider::class));
            }
        }

        if ((bool) config('corepanel.modules.auto_load_enabled', true)) {
            $this->app->make(ModuleManager::class)->loadEnabled();
        }

        $this->app->make(ModuleEventBridge::class)->register();

        if ((bool) config('corepanel.themes.auto_load_active', true)) {
            $this->app->make(ThemeManager::class)->applyEffective(null);
        }

        $gateways->sync();

        $this->app->make(ModuleDatabaseGuard::class)->register();

        Event::listen(OrderPaid::class, CreateServicesOnOrderPaid::class);
        Event::listen(TicketCreated::class, NotifyStaffOnTicketCreated::class);
        Event::listen(TicketReplied::class, NotifyParticipantsOnTicketReplied::class);
        Event::subscribe(DispatchOutgoingWebhooks::class);
        $this->app->make(AutomationEventBridge::class)->register();
        $this->app->make(WorkflowEngine::class)->register();
    }
}
