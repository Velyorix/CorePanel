<?php

namespace Tests\Feature\Modules;

use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModulePackageHasher;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Core\Modules\Services\ModuleSignatureVerifier;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Services\ProviderRegistry;
use Core\Services\Enums\ServiceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\ExampleProvider\Node\ExampleNodeProvider;
use Modules\ExampleProvider\Providers\ExampleProviderServiceProvider;
use Modules\ExampleProvider\Server\ExampleServerProvider;
use Tests\TestCase;

class ExampleProviderModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.modules.path' => base_path('Modules'),
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
            'corepanel.modules.resources.routes' => true,
            'corepanel.modules.resources.views' => true,
            'corepanel.modules.resources.migrations' => true,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);

        $this->rebindModuleManager();
        app(ProviderRegistry::class)->flush();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('module_example_demo_nodes');

        parent::tearDown();
    }

    public function test_example_provider_package_is_discovered(): void
    {
        $manifest = app(ModuleManager::class)->discover()->firstWhere('key', 'example');

        $this->assertNotNull($manifest);
        $this->assertSame('Example Provider', $manifest->name);
        $this->assertSame('1.0.0', $manifest->version);
        $this->assertTrue($manifest->hasCapability('server_provider'));
        $this->assertTrue($manifest->hasCapability('node_provider'));
        $this->assertContains(ExampleProviderServiceProvider::class, $manifest->providers);
    }

    public function test_enable_registers_server_and_node_providers(): void
    {
        $manager = app(ModuleManager::class);
        $manager->enable('example');

        $registry = app(ProviderRegistry::class);

        $this->assertTrue($registry->hasServer('example'));
        $this->assertTrue($registry->hasNode('example'));
        $this->assertInstanceOf(ExampleServerProvider::class, $registry->server('example'));
        $this->assertInstanceOf(ExampleNodeProvider::class, $registry->node('example'));
        $this->assertTrue($manager->isEnabled('example'));
        $this->assertTrue($manager->isLoaded('example'));
    }

    public function test_example_providers_return_deterministic_stub_responses(): void
    {
        app(ModuleManager::class)->enable('example');

        $server = app(ProviderRegistry::class)->server('example');
        $node = app(ProviderRegistry::class)->node('example');

        $provisioning = new ProvisioningRequest(
            serviceId: 42,
            clientId: 1,
            productId: 1,
            module: 'example',
            status: ServiceStatus::Pending,
        );

        $created = $server->create($provisioning);

        $this->assertTrue($created->isSuccessful());
        $this->assertSame('example-42', $created->externalId);
        $this->assertSame('service-42.example.local', $created->hostname);
        $this->assertSame('10.200.0.43', $created->ipAddress);

        $connection = new NodeConnectionRequest(
            hostname: 'node.example.local',
            id: 7,
            maxServices: 25,
        );

        $connected = $node->testConnection($connection);
        $resources = $node->getResources($connection);

        $this->assertSame(ProviderOperationStatus::Success, $connected->status);
        $this->assertSame(ProviderOperationStatus::Success, $resources->status);
        $this->assertSame(25, $resources->resources->maxServices);
    }

    public function test_example_module_auto_loads_routes_views_and_migrations(): void
    {
        app(ModuleManager::class)->load('example');

        $this->assertTrue(Route::has('module.example.status'));
        $this->assertSame(200, $this->get('/__example-provider/status')->getStatusCode());
        $this->assertTrue(view()->exists('example::status'));

        Artisan::call('migrate', ['--force' => true]);

        $this->assertTrue(Schema::hasTable('module_example_demo_nodes'));
    }

    public function test_disable_unloads_example_module(): void
    {
        $manager = app(ModuleManager::class);
        $manager->enable('example');
        $this->assertTrue($manager->isLoaded('example'));

        $manager->disable('example');

        $this->assertFalse($manager->isEnabled('example'));
        $this->assertFalse($manager->isLoaded('example'));
    }

    private function rebindModuleManager(): void
    {
        $this->app->forgetInstance(ModulePackageHasher::class);
        $this->app->forgetInstance(ModuleSignatureVerifier::class);
        $this->app->forgetInstance(InstalledModuleRepository::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleServiceProviderRegistrar::class);
        $this->app->forgetInstance(ModuleResourceLoader::class);
        $this->app->forgetInstance(ModuleManager::class);

        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(InstalledModuleRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            app(ModuleSandbox::class),
            app(ModuleServiceProviderRegistrar::class),
            app(ModuleResourceLoader::class),
            base_path('Modules'),
        ));
    }
}
