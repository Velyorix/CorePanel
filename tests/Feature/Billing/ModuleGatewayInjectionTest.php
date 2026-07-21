<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Core\Billing\Services\GatewayManager;
use Core\Billing\Services\PaymentGatewayInjector;
use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Exceptions\ModuleBootstrapException;
use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModulePackageHasher;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Core\Modules\Services\ModuleSignatureVerifier;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\Support\Billing\FakePluginGatewayRegistrar;
use Tests\Support\Modules\StubGatewayModuleServiceProvider;
use Tests\TestCase;

class ModuleGatewayInjectionTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        StubGatewayModuleServiceProvider::$booted = false;

        $this->modulesPath = storage_path('framework/testing/modules-gateways-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('c', 32)),
        ]);

        $this->rebindModuleStack();
        app(GatewayManager::class)->flush();
        app(PaymentGatewayInjector::class)->flushPlugins();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_payment_gateway_injector_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(PaymentGatewayInjector::class),
            app(PaymentGatewayInjector::class),
        );
    }

    public function test_module_manifest_gateways_are_registered_on_load(): void
    {
        $this->writeModule('manifest_gateway', [
            'name' => 'manifest_gateway',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::PaymentGateway->value],
            'gateways' => [FakePaymentGateway::class],
        ]);

        app(ModuleManager::class)->enable('manifest_gateway');

        $gateways = app(GatewayManager::class);

        $this->assertTrue($gateways->has('fake'));
        $this->assertInstanceOf(FakePaymentGateway::class, $gateways->resolve('fake', onlyEnabled: false));
        $this->assertContains(
            FakePaymentGateway::class,
            app(PaymentGatewayInjector::class)->gatewaysFor('manifest_gateway'),
        );

        $gateways->sync();
        $this->assertDatabaseHas('payment_gateways', [
            'key' => 'fake',
            'enabled' => false,
        ]);
    }

    public function test_module_service_provider_can_register_gateway_via_helper(): void
    {
        $this->writeModule('gateway_demo', [
            'name' => 'gateway_demo',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::PaymentGateway->value],
            'providers' => [StubGatewayModuleServiceProvider::class],
        ]);

        app(ModuleManager::class)->enable('gateway_demo');

        $this->assertTrue(StubGatewayModuleServiceProvider::$booted);
        $this->assertTrue(app(GatewayManager::class)->has('provider_fake'));
        $this->assertSame(
            'Provider Fake Gateway',
            app(GatewayManager::class)->resolve('provider_fake', onlyEnabled: false)->label(),
        );
    }

    public function test_plugin_hook_registers_gateway_on_boot_plugins(): void
    {
        $injector = app(PaymentGatewayInjector::class);
        $injector->registerPlugin(new FakePluginGatewayRegistrar);

        $this->assertSame(1, $injector->bootPlugins());
        $this->assertTrue(app(GatewayManager::class)->has('plugin_fake'));
        $this->assertSame(
            'Plugin Fake Gateway',
            app(GatewayManager::class)->resolve('plugin_fake', onlyEnabled: false)->label(),
        );
    }

    public function test_module_injected_gateway_is_visible_and_activatable_in_admin(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.module-gateway-admin',
        ]);

        $this->writeModule('admin_gateway', [
            'name' => 'admin_gateway',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::PaymentGateway->value],
            'gateways' => [FakePaymentGateway::class],
        ]);

        app(ModuleManager::class)->enable('admin_gateway');

        $gateways = app(GatewayManager::class);
        $gateways->sync();

        $this->assertTrue($gateways->has('fake'));
        $this->assertFalse($gateways->isEnabled('fake'));

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.gateways.index'))
            ->assertOk()
            ->assertSee('fake')
            ->assertSee('Fake Gateway');

        $this->actingAs($admin)
            ->post(route('admin.gateways.enable', 'fake'))
            ->assertRedirect(route('admin.gateways.show', 'fake'));

        $this->assertTrue($gateways->isEnabled('fake'));
        $this->assertDatabaseHas('payment_gateways', [
            'key' => 'fake',
            'enabled' => true,
        ]);
    }

    public function test_invalid_gateway_class_in_manifest_fails_load(): void
    {
        $this->writeModule('bad_gateway', [
            'name' => 'bad_gateway',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::PaymentGateway->value],
            'gateways' => [\stdClass::class],
        ]);

        $this->expectException(ModuleBootstrapException::class);
        $this->expectExceptionMessage('must implement Core\\Billing\\Contracts\\PaymentGateway');

        app(ModuleManager::class)->load('bad_gateway');
    }

    public function test_missing_gateway_class_in_manifest_fails_load(): void
    {
        $this->writeModule('ghost_gateway', [
            'name' => 'ghost_gateway',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::PaymentGateway->value],
            'gateways' => ['Modules\\Ghost\\MissingGateway'],
        ]);

        $this->expectException(ModuleBootstrapException::class);
        $this->expectExceptionMessage('could not be found');

        app(ModuleManager::class)->load('ghost_gateway');
    }

    private function rebindModuleStack(): void
    {
        $this->app->forgetInstance(ModulePackageHasher::class);
        $this->app->forgetInstance(ModuleSignatureVerifier::class);
        $this->app->forgetInstance(InstalledModuleRepository::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleServiceProviderRegistrar::class);
        $this->app->forgetInstance(ModuleResourceLoader::class);
        $this->app->forgetInstance(PaymentGatewayInjector::class);
        $this->app->forgetInstance(ModuleManager::class);

        $this->app->singleton(PaymentGatewayInjector::class);
        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(InstalledModuleRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            app(ModuleSandbox::class),
            app(ModuleServiceProviderRegistrar::class),
            app(ModuleResourceLoader::class),
            $this->modulesPath,
            app(PaymentGatewayInjector::class),
        ));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeModule(string $directory, array $manifest): void
    {
        $path = $this->modulesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }
}
