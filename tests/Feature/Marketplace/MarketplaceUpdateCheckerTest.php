<?php

namespace Tests\Feature\Marketplace;

use Core\Billing\Services\PaymentGatewayInjector;
use Core\Marketplace\Jobs\CheckMarketplaceUpdatesJob;
use Core\Marketplace\Services\MarketplaceCatalogCache;
use Core\Marketplace\Services\MarketplaceClient;
use Core\Marketplace\Services\MarketplaceCompatibilityGuard;
use Core\Marketplace\Services\MarketplacePackageOriginStore;
use Core\Marketplace\Services\MarketplaceUpdateChecker;
use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleHookRegistry;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModulePackageHasher;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Core\Modules\Services\ModuleSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceUpdateCheckerTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/marketplace-updates-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.org.api_url' => 'https://corepanel.org/api/v1',
            'corepanel.org.api_token' => 'cpat_test_token_12345678901234567890',
            'corepanel.version' => '1.2.0',
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
            'corepanel.marketplace.enabled' => true,
            'corepanel.marketplace.cache.enabled' => false,
            'corepanel.marketplace.cache.store' => 'array',
            'corepanel.marketplace.cache.prefix' => 'test.marketplace',
            'corepanel.marketplace.updates.enabled' => true,
            'corepanel.marketplace.updates.cache_ttl_seconds' => 3600,
            'corepanel.marketplace.compatibility.enforce' => true,
        ]);

        Cache::store('array')->flush();
        $this->rebindServices();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_detects_newer_compatible_marketplace_version(): void
    {
        $this->installTrackedModule('demo_mod', '1.0.0', 'marketplace-demo', 'MOD_DEMO');

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/marketplace-demo' => Http::response([
                'data' => $this->productPayload('marketplace-demo', 'MOD_DEMO', '2.0.0'),
            ], 200),
            'https://corepanel.org/api/v1/marketplace/products/marketplace-demo/versions' => Http::response([
                'product' => ['slug' => 'marketplace-demo', 'name' => 'Demo'],
                'data' => [
                    $this->versionPayload('1.0.0', false),
                    $this->versionPayload('2.0.0', true, '1.0.0'),
                ],
            ], 200),
        ]);

        $result = app(MarketplaceUpdateChecker::class)->check();

        $this->assertTrue($result->hasUpdates());
        $this->assertSame(1, $result->checkedCount);
        $this->assertCount(1, $result->updates);
        $this->assertSame('2.0.0', $result->updates[0]->availableVersion);
        $this->assertSame('1.0.0', $result->updates[0]->installedVersion);
        $this->assertTrue($result->updates[0]->compatible);

        $cached = app(MarketplaceUpdateChecker::class)->latestResult();
        $this->assertNotNull($cached);
        $this->assertSame('2.0.0', $cached->updates[0]->availableVersion);
    }

    public function test_returns_no_updates_when_already_on_latest(): void
    {
        $this->installTrackedModule('demo_mod', '2.0.0', 'marketplace-demo', 'MOD_DEMO');

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/marketplace-demo' => Http::response([
                'data' => $this->productPayload('marketplace-demo', 'MOD_DEMO', '2.0.0'),
            ], 200),
            'https://corepanel.org/api/v1/marketplace/products/marketplace-demo/versions' => Http::response([
                'product' => ['slug' => 'marketplace-demo', 'name' => 'Demo'],
                'data' => [
                    $this->versionPayload('1.0.0', false),
                    $this->versionPayload('2.0.0', true),
                ],
            ], 200),
        ]);

        $result = app(MarketplaceUpdateChecker::class)->check();

        $this->assertFalse($result->hasUpdates());
        $this->assertSame(1, $result->checkedCount);
        $this->assertSame([], $result->updates);
    }

    public function test_prefers_compatible_update_over_newer_incompatible(): void
    {
        $this->installTrackedModule('demo_mod', '1.0.0', 'marketplace-demo', 'MOD_DEMO');

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/marketplace-demo' => Http::response([
                'data' => $this->productPayload('marketplace-demo', 'MOD_DEMO', '3.0.0'),
            ], 200),
            'https://corepanel.org/api/v1/marketplace/products/marketplace-demo/versions' => Http::response([
                'product' => ['slug' => 'marketplace-demo', 'name' => 'Demo'],
                'data' => [
                    $this->versionPayload('1.5.0', false, '1.0.0'),
                    $this->versionPayload('3.0.0', true, '9.0.0'),
                ],
            ], 200),
        ]);

        $result = app(MarketplaceUpdateChecker::class)->check();

        $this->assertCount(1, $result->updates);
        $this->assertSame('1.5.0', $result->updates[0]->availableVersion);
        $this->assertTrue($result->updates[0]->compatible);
    }

    public function test_reports_incompatible_update_when_no_compatible_candidate(): void
    {
        $this->installTrackedModule('demo_mod', '1.0.0', 'marketplace-demo', 'MOD_DEMO');

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/marketplace-demo' => Http::response([
                'data' => $this->productPayload('marketplace-demo', 'MOD_DEMO', '3.0.0'),
            ], 200),
            'https://corepanel.org/api/v1/marketplace/products/marketplace-demo/versions' => Http::response([
                'product' => ['slug' => 'marketplace-demo', 'name' => 'Demo'],
                'data' => [
                    $this->versionPayload('3.0.0', true, '9.0.0'),
                ],
            ], 200),
        ]);

        $result = app(MarketplaceUpdateChecker::class)->check();

        $this->assertCount(1, $result->updates);
        $this->assertSame('3.0.0', $result->updates[0]->availableVersion);
        $this->assertFalse($result->updates[0]->compatible);
    }

    public function test_skips_origin_when_marketplace_api_fails(): void
    {
        $this->installTrackedModule('demo_mod', '1.0.0', 'marketplace-demo', 'MOD_DEMO');

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/marketplace-demo' => Http::response([
                'error' => ['message' => 'boom'],
            ], 500),
        ]);

        $result = app(MarketplaceUpdateChecker::class)->check();

        $this->assertSame(1, $result->checkedCount);
        $this->assertFalse($result->hasUpdates());
    }

    public function test_job_is_noop_when_updates_disabled(): void
    {
        config(['corepanel.marketplace.updates.enabled' => false]);
        $this->installTrackedModule('demo_mod', '1.0.0', 'marketplace-demo', 'MOD_DEMO');

        Http::fake(function (): never {
            $this->fail('Marketplace API should not be called when updates are disabled.');
        });

        (new CheckMarketplaceUpdatesJob)->handle(app(MarketplaceUpdateChecker::class));

        $this->assertNull(app(MarketplaceUpdateChecker::class)->latestResult());
    }

    public function test_job_is_noop_when_marketplace_disabled(): void
    {
        config(['corepanel.marketplace.enabled' => false]);
        $this->installTrackedModule('demo_mod', '1.0.0', 'marketplace-demo', 'MOD_DEMO');

        Http::fake(function (): never {
            $this->fail('Marketplace API should not be called when marketplace is disabled.');
        });

        (new CheckMarketplaceUpdatesJob)->handle(app(MarketplaceUpdateChecker::class));

        $this->assertNull(app(MarketplaceUpdateChecker::class)->latestResult());
    }

    private function installTrackedModule(string $key, string $version, string $slug, string $sku): void
    {
        $directory = str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        $path = $this->modulesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/module.json',
            json_encode([
                'name' => $key,
                'version' => $version,
                'label' => $key,
                'capabilities' => ['other'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        app(ModuleManager::class)->discover(refresh: true);
        app(ModuleManager::class)->install($key);

        app(MarketplacePackageOriginStore::class)->remember(
            productType: 'module',
            packageKey: $key,
            slug: $slug,
            sku: $sku,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $slug, string $sku, string $currentVersion): array
    {
        return [
            'id' => '11111111-1111-1111-1111-111111111111',
            'sku' => $sku,
            'slug' => $slug,
            'name' => $slug,
            'product_type' => 'module',
            'pricing' => ['is_free' => true, 'amount' => 0, 'currency' => 'EUR'],
            'current_version' => $currentVersion,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(string $version, bool $latest, ?string $minCms = '1.0.0'): array
    {
        return [
            'id' => 'v-'.$version,
            'version' => $version,
            'is_latest' => $latest,
            'has_archive' => true,
            'archive_size_bytes' => 100,
            'compatibility' => [
                'min_version' => $minCms,
                'max_version' => null,
            ],
        ];
    }

    private function rebindServices(): void
    {
        $this->app->forgetInstance(ModulePackageHasher::class);
        $this->app->forgetInstance(ModuleSignatureVerifier::class);
        $this->app->forgetInstance(InstalledModuleRepository::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleServiceProviderRegistrar::class);
        $this->app->forgetInstance(ModuleResourceLoader::class);
        $this->app->forgetInstance(PaymentGatewayInjector::class);
        $this->app->forgetInstance(ModuleHookRegistry::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(MarketplaceCatalogCache::class);
        $this->app->forgetInstance(MarketplaceClient::class);
        $this->app->forgetInstance(MarketplaceCompatibilityGuard::class);
        $this->app->forgetInstance(MarketplacePackageOriginStore::class);
        $this->app->forgetInstance(MarketplaceUpdateChecker::class);

        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(InstalledModuleRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            app(ModuleSandbox::class),
            app(ModuleServiceProviderRegistrar::class),
            app(ModuleResourceLoader::class),
            $this->modulesPath,
            app(PaymentGatewayInjector::class),
            app(ModuleHookRegistry::class),
        ));
    }
}
