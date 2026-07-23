<?php

namespace Tests\Feature\Marketplace;

use Core\Billing\Services\PaymentGatewayInjector;
use Core\License\Models\LicenseActivation;
use Core\License\Services\LicenseSettings;
use Core\Marketplace\Exceptions\MarketplaceCompatibilityException;
use Core\Marketplace\Exceptions\MarketplaceEntitlementException;
use Core\Marketplace\Exceptions\MarketplaceInstallException;
use Core\Marketplace\Services\MarketplaceCatalogCache;
use Core\Marketplace\Services\MarketplaceClient;
use Core\Marketplace\Services\MarketplaceCompatibilityGuard;
use Core\Marketplace\Services\MarketplaceEntitlementGuard;
use Core\Marketplace\Services\MarketplaceInstaller;
use Core\Marketplace\Services\MarketplacePackageIntegrityGuard;
use Core\Marketplace\Services\MarketplacePackageOriginStore;
use Core\Modules\Models\InstalledModule;
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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class MarketplaceInstallerTest extends TestCase
{
    use RefreshDatabase;

    private const INTEGRITY_SECRET = 'test-marketplace-secret';

    protected bool $configureValidLicenseByDefault = false;

    private string $modulesPath;

    private string $themesPath;

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->modulesPath = storage_path('framework/testing/marketplace-modules-'.uniqid('', true));
        $this->themesPath = storage_path('framework/testing/marketplace-themes-'.uniqid('', true));
        $this->tempPath = storage_path('framework/testing/marketplace-tmp-'.uniqid('', true));

        File::ensureDirectoryExists($this->modulesPath);
        File::ensureDirectoryExists($this->themesPath);
        File::ensureDirectoryExists($this->tempPath);

        config([
            'corepanel.org.api_url' => 'https://corepanel.org/api/v1',
            'corepanel.org.api_token' => 'cpat_test_token_12345678901234567890',
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
            'corepanel.marketplace.entitlements.enforce' => true,
            'corepanel.marketplace.entitlements.allow_free_without_entitlement' => true,
            'corepanel.marketplace.compatibility.enforce' => true,
            'corepanel.marketplace.integrity.enforce_checksum' => true,
            'corepanel.marketplace.integrity.verify_signature' => true,
            'corepanel.marketplace.integrity.enforce_signature' => true,
            'corepanel.marketplace.integrity.signature_secret' => self::INTEGRITY_SECRET,
            'corepanel.marketplace.install.temp_path' => $this->tempPath,
            'corepanel.version' => '1.2.0',
        ]);

        $this->rebindServices();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        File::deleteDirectory($this->themesPath);
        File::deleteDirectory($this->tempPath);

        parent::tearDown();
    }

    public function test_installs_free_module_from_marketplace_archive(): void
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
        );

        $result = app(MarketplaceInstaller::class)->install('marketplace-demo', enable: true);

        $this->assertSame('marketplace-demo', $result->productSlug);
        $this->assertSame('module', $result->productType);
        $this->assertSame('1.0.0', $result->version);
        $this->assertSame('marketplace_demo', $result->packageKey);
        $this->assertTrue($result->enabled);
        $this->assertFileExists($this->modulesPath.'/MarketplaceDemo/module.json');
        $this->assertDatabaseHas('installed_modules', [
            'name' => 'marketplace_demo',
            'enabled' => true,
        ]);
        $this->assertTrue(app(ModuleManager::class)->isLoaded('marketplace_demo'));

        $origins = app(MarketplacePackageOriginStore::class)->all();
        $this->assertCount(1, $origins);
        $this->assertSame('module', $origins[0]['product_type']);
        $this->assertSame('marketplace_demo', $origins[0]['package_key']);
        $this->assertSame('marketplace-demo', $origins[0]['slug']);
        $this->assertSame('MOD_MARKETPLACE_DEMO', $origins[0]['sku']);
    }

    public function test_installs_paid_module_when_entitled(): void
    {
        $this->activateLicense([[
            'product_type' => 'module',
            'product_sku' => 'stripe-billing-pro',
            'product_name' => 'Stripe Billing Pro',
        ]]);

        $zipContents = $this->makeModuleZip('StripeBillingPro', [
            'name' => 'stripe_billing_pro',
            'version' => '1.2.0',
            'capabilities' => ['payment_gateway'],
        ]);

        $this->fakeMarketplacePackage(
            slug: 'stripe-billing-pro',
            productType: 'module',
            sku: 'MOD_STRIPE_BILLING_PRO',
            free: false,
            version: '1.2.0',
            zipContents: $zipContents,
        );

        $result = app(MarketplaceInstaller::class)->install('stripe-billing-pro');

        $this->assertSame('stripe_billing_pro', $result->packageKey);
        $this->assertFalse($result->enabled);
        $this->assertInstanceOf(InstalledModule::class, app(ModuleManager::class)->installation('stripe_billing_pro'));
    }

    public function test_blocks_paid_module_install_without_entitlement(): void
    {
        $this->activateLicense([]);

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/stripe-billing-pro' => Http::response([
                'data' => [
                    'id' => '1',
                    'sku' => 'MOD_STRIPE',
                    'slug' => 'stripe-billing-pro',
                    'name' => 'Stripe Billing Pro',
                    'product_type' => 'module',
                    'pricing' => ['is_free' => false, 'amount' => 2990, 'currency' => 'EUR'],
                    'current_version' => '1.0.0',
                ],
            ], 200),
        ]);

        $this->expectException(MarketplaceEntitlementException::class);

        app(MarketplaceInstaller::class)->install('stripe-billing-pro');
    }

    public function test_installs_theme_package_from_marketplace_archive(): void
    {
        $zipContents = $this->makeThemeZip('OceanBlue', [
            'name' => 'ocean-blue',
            'label' => 'Ocean Blue',
            'version' => '2.0.0',
        ]);

        $this->fakeMarketplacePackage(
            slug: 'ocean-blue',
            productType: 'theme',
            sku: 'THM_OCEAN_BLUE',
            free: true,
            version: '2.0.0',
            zipContents: $zipContents,
        );

        $result = app(MarketplaceInstaller::class)->install('ocean-blue', enable: true);

        $this->assertSame('theme', $result->productType);
        $this->assertSame('ocean-blue', $result->packageKey);
        $this->assertTrue($result->enabled);
        $this->assertFileExists($this->themesPath.'/OceanBlue/theme.json');
        $this->assertSame('ocean-blue', app(ThemeManager::class)->activeKey());
    }

    public function test_rejects_checksum_mismatch(): void
    {
        $zipContents = $this->makeModuleZip('BadChecksum', [
            'name' => 'bad_checksum',
            'version' => '1.0.0',
            'capabilities' => ['other'],
        ]);

        $this->fakeMarketplacePackage(
            slug: 'bad-checksum',
            productType: 'module',
            sku: 'MOD_BAD',
            free: true,
            version: '1.0.0',
            zipContents: $zipContents,
            checksum: str_repeat('a', 64),
        );

        $this->expectException(MarketplaceInstallException::class);
        $this->expectExceptionMessage('checksum mismatch');

        app(MarketplaceInstaller::class)->install('bad-checksum');
    }

    public function test_rejects_invalid_download_signature(): void
    {
        $zipContents = $this->makeModuleZip('BadSignature', [
            'name' => 'bad_signature',
            'version' => '1.0.0',
            'capabilities' => ['other'],
        ]);

        $this->fakeMarketplacePackage(
            slug: 'bad-signature',
            productType: 'module',
            sku: 'MOD_BAD_SIG',
            free: true,
            version: '1.0.0',
            zipContents: $zipContents,
            signature: str_repeat('f', 64),
        );

        $this->expectException(MarketplaceInstallException::class);
        $this->expectExceptionMessage('signature is invalid');

        app(MarketplaceInstaller::class)->install('bad-signature');
    }

    public function test_rejects_missing_checksum_when_enforced(): void
    {
        $zipContents = $this->makeModuleZip('MissingChecksum', [
            'name' => 'missing_checksum',
            'version' => '1.0.0',
            'capabilities' => ['other'],
        ]);

        $this->fakeMarketplacePackage(
            slug: 'missing-checksum',
            productType: 'module',
            sku: 'MOD_MISSING_CHECKSUM',
            free: true,
            version: '1.0.0',
            zipContents: $zipContents,
            withChecksum: false,
            withSignature: false,
        );

        $this->expectException(MarketplaceInstallException::class);
        $this->expectExceptionMessage('missing a required SHA-256 checksum');

        app(MarketplaceInstaller::class)->install('missing-checksum');
    }

    public function test_rejects_size_mismatch(): void
    {
        $zipContents = $this->makeModuleZip('SizeMismatch', [
            'name' => 'size_mismatch',
            'version' => '1.0.0',
            'capabilities' => ['other'],
        ]);

        $this->fakeMarketplacePackage(
            slug: 'size-mismatch',
            productType: 'module',
            sku: 'MOD_SIZE',
            free: true,
            version: '1.0.0',
            zipContents: $zipContents,
            sizeBytes: strlen($zipContents) + 512,
        );

        $this->expectException(MarketplaceInstallException::class);
        $this->expectExceptionMessage('size mismatch');

        app(MarketplaceInstaller::class)->install('size-mismatch');
    }

    public function test_rejects_when_destination_already_exists(): void
    {
        File::ensureDirectoryExists($this->modulesPath.'/ExistingModule');
        File::put($this->modulesPath.'/ExistingModule/module.json', json_encode([
            'name' => 'existing_module',
            'version' => '1.0.0',
            'capabilities' => ['other'],
        ]));

        $zipContents = $this->makeModuleZip('ExistingModule', [
            'name' => 'existing_module',
            'version' => '1.1.0',
            'capabilities' => ['other'],
        ]);

        $this->fakeMarketplacePackage(
            slug: 'existing-module',
            productType: 'module',
            sku: 'MOD_EXISTING',
            free: true,
            version: '1.1.0',
            zipContents: $zipContents,
        );

        $this->expectException(MarketplaceInstallException::class);
        $this->expectExceptionMessage('already exists');

        app(MarketplaceInstaller::class)->install('existing-module');
    }

    public function test_blocks_install_when_cms_version_is_incompatible(): void
    {
        config(['corepanel.version' => '0.9.0']);

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/needs-v2' => Http::response([
                'data' => [
                    'id' => '1',
                    'sku' => 'MOD_NEEDS_V2',
                    'slug' => 'needs-v2',
                    'name' => 'Needs V2',
                    'product_type' => 'module',
                    'pricing' => ['is_free' => true, 'amount' => 0, 'currency' => 'EUR'],
                    'current_version' => '1.0.0',
                    'compatibility' => ['min_version' => '2.0.0'],
                ],
            ], 200),
            'https://corepanel.org/api/v1/marketplace/products/needs-v2/versions' => Http::response([
                'product' => ['slug' => 'needs-v2', 'name' => 'Needs V2'],
                'data' => [[
                    'id' => 'v1',
                    'version' => '1.0.0',
                    'is_latest' => true,
                    'has_archive' => true,
                    'compatibility' => ['min_version' => '2.0.0'],
                ]],
            ], 200),
        ]);

        try {
            app(MarketplaceInstaller::class)->install('needs-v2');
            $this->fail('Expected MarketplaceCompatibilityException was not thrown.');
        } catch (MarketplaceCompatibilityException $exception) {
            $this->assertFalse($exception->decision->compatible);
            $this->assertStringContainsString('2.0.0', $exception->getMessage());
        }

        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), '/download');
        });
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function makeModuleZip(string $directory, array $manifest): string
    {
        return $this->makeZip($directory, 'module.json', $manifest, [
            'README.md' => "# {$directory}\n",
        ]);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function makeThemeZip(string $directory, array $manifest): string
    {
        return $this->makeZip($directory, 'theme.json', $manifest, [
            'resources/views/.gitkeep' => '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $extraFiles
     */
    private function makeZip(string $directory, string $manifestFilename, array $manifest, array $extraFiles = []): string
    {
        $zipPath = $this->tempPath.'/fixture-'.uniqid('', true).'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString(
            $directory.'/'.$manifestFilename,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        foreach ($extraFiles as $relative => $contents) {
            $zip->addFromString($directory.'/'.$relative, $contents);
        }

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
        ?string $checksum = null,
        ?string $signature = null,
        bool $withChecksum = true,
        bool $withSignature = true,
        ?int $sizeBytes = null,
    ): void {
        $checksum ??= hash('sha256', $zipContents);
        $sizeBytes ??= strlen($zipContents);

        if ($withSignature) {
            $signature ??= $this->signDownloadChecksum($checksum);
        }

        $cdnUrl = 'https://cdn.corepanel.test/packages/'.$slug.'-'.$version.'.zip';
        $downloadPayload = [
            'download_url' => $cdnUrl,
            'filename' => $slug.'-'.$version.'.zip',
            'size_bytes' => $sizeBytes,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];

        if ($withChecksum) {
            $downloadPayload['checksum_sha256'] = $checksum;
        }

        if ($withSignature && $signature !== null) {
            $downloadPayload['signature_hmac_sha256'] = $signature;
        }

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/'.$slug => Http::response([
                'data' => [
                    'id' => '11111111-1111-1111-1111-111111111111',
                    'sku' => $sku,
                    'slug' => $slug,
                    'name' => $slug,
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
                'product' => ['slug' => $slug, 'name' => $slug],
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
                'data' => $downloadPayload,
            ], 200),
            $cdnUrl => Http::response($zipContents, 200, [
                'Content-Type' => 'application/zip',
            ]),
        ]);
    }

    private function signDownloadChecksum(string $checksum): string
    {
        return hash_hmac('sha256', strtolower($checksum), self::INTEGRITY_SECRET);
    }

    /**
     * @param  list<array<string, mixed>>  $entitlements
     */
    private function activateLicense(array $entitlements): void
    {
        app(LicenseSettings::class)->setInstanceId('cms-marketplace-install-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-marketplace-install-01',
            'status' => 'active',
            'entitlements' => $entitlements,
            'updated_at' => now(),
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
