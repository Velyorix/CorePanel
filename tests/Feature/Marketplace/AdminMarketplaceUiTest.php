<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use Core\Billing\Services\PaymentGatewayInjector;
use Core\Marketplace\DataTransferObjects\MarketplaceAvailableUpdate;
use Core\Marketplace\DataTransferObjects\MarketplaceUpdateCheckResult;
use Core\Marketplace\Services\MarketplaceCatalogCache;
use Core\Marketplace\Services\MarketplaceClient;
use Core\Marketplace\Services\MarketplaceCompatibilityGuard;
use Core\Marketplace\Services\MarketplaceEntitlementGuard;
use Core\Marketplace\Services\MarketplaceInstaller;
use Core\Marketplace\Services\MarketplacePackageIntegrityGuard;
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
use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeStateRepository;
use Core\Themes\Services\ThemeViewRegistrar;
use Core\Themes\Services\ThemeViteEntryResolver;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class AdminMarketplaceUiTest extends TestCase
{
    use RefreshDatabase;

    private const INTEGRITY_SECRET = 'test-marketplace-secret';

    private string $modulesPath;

    private string $themesPath;

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->modulesPath = storage_path('framework/testing/marketplace-admin-modules-'.uniqid('', true));
        $this->themesPath = storage_path('framework/testing/marketplace-admin-themes-'.uniqid('', true));
        $this->tempPath = storage_path('framework/testing/marketplace-admin-tmp-'.uniqid('', true));

        File::ensureDirectoryExists($this->modulesPath);
        File::ensureDirectoryExists($this->themesPath);
        File::ensureDirectoryExists($this->tempPath);

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
            'corepanel.themes.path' => $this->themesPath,
            'corepanel.themes.auto_load_active' => false,
            'corepanel.marketplace.enabled' => true,
            'corepanel.marketplace.cache.enabled' => false,
            'corepanel.marketplace.cache.store' => 'array',
            'corepanel.marketplace.cache.prefix' => 'test.marketplace.admin',
            'corepanel.marketplace.entitlements.enforce' => true,
            'corepanel.marketplace.entitlements.allow_free_without_entitlement' => true,
            'corepanel.marketplace.compatibility.enforce' => true,
            'corepanel.marketplace.updates.enabled' => true,
            'corepanel.marketplace.integrity.enforce_checksum' => true,
            'corepanel.marketplace.integrity.verify_signature' => true,
            'corepanel.marketplace.integrity.enforce_signature' => true,
            'corepanel.marketplace.integrity.signature_secret' => self::INTEGRITY_SECRET,
            'corepanel.marketplace.install.temp_path' => $this->tempPath,
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.marketplace-admin',
        ]);

        Cache::store('array')->flush();
        $this->configureValidLicense();
        $this->withoutVite();
        $this->rebindServices();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        File::deleteDirectory($this->themesPath);
        File::deleteDirectory($this->tempPath);

        parent::tearDown();
    }

    public function test_admin_can_browse_marketplace_catalogue(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::response([
                'data' => [[
                    'id' => '1',
                    'sku' => 'MOD_DEMO',
                    'slug' => 'marketplace-demo',
                    'name' => 'Marketplace Demo',
                    'short_description' => 'Demo package',
                    'product_type' => 'module',
                    'category' => ['slug' => 'billing', 'name' => 'Billing'],
                    'developer' => ['name' => 'Velyorix'],
                    'pricing' => ['is_free' => true, 'amount' => 0, 'currency' => 'EUR'],
                    'current_version' => '1.0.0',
                ]],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
            ], 200),
        ]);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.marketplace.index'))
            ->assertOk()
            ->assertSee('Marketplace Demo')
            ->assertSee('marketplace-demo')
            ->assertSee('1.0.0')
            ->assertSee(__('Install'));
    }

    public function test_admin_can_view_product_and_install_free_module(): void
    {
        $zipContents = $this->makeModuleZip('MarketplaceDemo', [
            'name' => 'marketplace_demo',
            'version' => '1.0.0',
            'label' => 'Marketplace Demo',
            'capabilities' => ['other'],
        ]);

        $this->fakeMarketplacePackage(
            slug: 'marketplace-demo',
            productType: 'module',
            sku: 'MOD_MARKETPLACE_DEMO',
            free: true,
            version: '1.0.0',
            zipContents: $zipContents,
            name: 'Marketplace Demo',
        );

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.marketplace.show', 'marketplace-demo'))
            ->assertOk()
            ->assertSee('Marketplace Demo')
            ->assertSee('MOD_MARKETPLACE_DEMO')
            ->assertSee(__('Compatible'));

        $this->actingAs($admin)
            ->post(route('admin.marketplace.install', 'marketplace-demo'), [
                'enable' => '1',
            ])
            ->assertRedirect(route('admin.marketplace.show', 'marketplace-demo'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('installed_modules', [
            'name' => 'marketplace_demo',
            'enabled' => true,
        ]);
        $this->assertFileExists($this->modulesPath.'/MarketplaceDemo/module.json');
    }

    public function test_forbidden_without_marketplace_view_permission(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.marketplace.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_updates_and_run_check(): void
    {
        $prefix = (string) config('corepanel.marketplace.cache.prefix');
        Cache::store('array')->put($prefix.'.updates.result', (new MarketplaceUpdateCheckResult(
            updates: [
                new MarketplaceAvailableUpdate(
                    productType: 'module',
                    packageKey: 'demo_mod',
                    marketplaceSlug: 'marketplace-demo',
                    installedVersion: '1.0.0',
                    availableVersion: '2.0.0',
                    compatible: true,
                    sku: 'MOD_DEMO',
                ),
            ],
            checkedCount: 1,
            checkedAt: '2026-07-23T10:00:00+00:00',
        ))->toArray(), now()->addHour());

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.marketplace.updates'))
            ->assertOk()
            ->assertSee('demo_mod')
            ->assertSee('2.0.0')
            ->assertSee(__('Compatible'));

        app(MarketplacePackageOriginStore::class)->remember(
            productType: 'module',
            packageKey: 'missing_mod',
            slug: 'missing-product',
            sku: 'MOD_MISSING',
        );

        $this->actingAs($admin)
            ->post(route('admin.marketplace.updates.check'))
            ->assertRedirect(route('admin.marketplace.updates'))
            ->assertSessionHas('status');
    }

    public function test_navigation_links_marketplace_for_admin(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $item = collect(app(\Core\Admin\Navigation\AdminNavigation::class)->forUser($admin))
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Marketplace'));

        $this->assertNotNull($item);
        $this->assertFalse($item['placeholder']);
        $this->assertSame(route('admin.marketplace.index'), $item['url']);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function makeModuleZip(string $directory, array $manifest): string
    {
        $zipPath = $this->tempPath.'/fixture-'.uniqid('', true).'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString(
            $directory.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
        $zip->addFromString($directory.'/README.md', "# {$directory}\n");
        $zip->close();

        return (string) file_get_contents($zipPath);
    }

    private function fakeMarketplacePackage(
        string $slug,
        string $productType,
        string $sku,
        bool $free,
        string $version,
        string $zipContents,
        ?string $name = null,
    ): void {
        $checksum = hash('sha256', $zipContents);
        $signature = hash_hmac('sha256', strtolower($checksum), self::INTEGRITY_SECRET);
        $cdnUrl = 'https://cdn.corepanel.test/packages/'.$slug.'-'.$version.'.zip';
        $name ??= $slug;

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/'.$slug => Http::response([
                'data' => [
                    'id' => '11111111-1111-1111-1111-111111111111',
                    'sku' => $sku,
                    'slug' => $slug,
                    'name' => $name,
                    'product_type' => $productType,
                    'pricing' => [
                        'is_free' => $free,
                        'amount' => $free ? 0 : 2990,
                        'currency' => 'EUR',
                    ],
                    'current_version' => $version,
                ],
            ], 200),
            'https://corepanel.org/api/v1/marketplace/products/'.$slug.'/versions' => Http::response([
                'product' => ['slug' => $slug, 'name' => $name],
                'data' => [[
                    'id' => 'v1',
                    'version' => $version,
                    'is_latest' => true,
                    'has_archive' => true,
                    'archive_size_bytes' => strlen($zipContents),
                    'compatibility' => ['min_version' => '1.0.0', 'max_version' => null],
                ]],
            ], 200),
            'https://corepanel.org/api/v1/marketplace/products/'.$slug.'/versions/'.$version.'/download' => Http::response([
                'data' => [
                    'download_url' => $cdnUrl,
                    'filename' => $slug.'-'.$version.'.zip',
                    'checksum_sha256' => $checksum,
                    'signature_hmac_sha256' => $signature,
                    'size_bytes' => strlen($zipContents),
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ], 200),
            $cdnUrl => Http::response($zipContents, 200, [
                'Content-Type' => 'application/zip',
            ]),
        ]);
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
        $this->app->forgetInstance(ThemeStateRepository::class);
        $this->app->forgetInstance(ThemeViewRegistrar::class);
        $this->app->forgetInstance(ThemeViteEntryResolver::class);
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetInstance(MarketplaceCatalogCache::class);
        $this->app->forgetInstance(MarketplaceClient::class);
        $this->app->forgetInstance(MarketplaceEntitlementGuard::class);
        $this->app->forgetInstance(MarketplaceCompatibilityGuard::class);
        $this->app->forgetInstance(MarketplacePackageIntegrityGuard::class);
        $this->app->forgetInstance(MarketplacePackageOriginStore::class);
        $this->app->forgetInstance(MarketplaceInstaller::class);
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
