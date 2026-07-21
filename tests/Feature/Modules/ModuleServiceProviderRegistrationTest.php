<?php

namespace Tests\Feature\Modules;

use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Exceptions\ModuleBootstrapException;
use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\Modules\StubExampleModule;
use Tests\Support\Modules\StubExampleServiceProvider;
use Tests\TestCase;

class ModuleServiceProviderRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        StubExampleServiceProvider::$registered = false;
        StubExampleServiceProvider::$booted = false;

        $this->modulesPath = storage_path('framework/testing/modules-providers-'.uniqid('', true));

        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
        ]);

        $this->app->forgetInstance(InstalledModuleRepository::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleServiceProviderRegistrar::class);
        $this->app->forgetInstance(ModuleManager::class);

        $this->app->singleton(ModuleServiceProviderRegistrar::class);
        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(InstalledModuleRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            app(ModuleSandbox::class),
            app(ModuleServiceProviderRegistrar::class),
            app(ModuleResourceLoader::class),
            $this->modulesPath,
        ));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_provider_registrar_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ModuleServiceProviderRegistrar::class),
            app(ModuleServiceProviderRegistrar::class),
        );
    }

    public function test_loads_providers_from_manifest_before_module_hooks(): void
    {
        $this->writeModule('provider_demo', [
            'name' => 'provider_demo',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::Other->value],
            'module' => StubExampleModule::class,
            'providers' => [StubExampleServiceProvider::class],
        ]);

        $manager = app(ModuleManager::class);
        $manager->load('provider_demo');

        $this->assertTrue(StubExampleServiceProvider::$registered);
        $this->assertTrue(StubExampleServiceProvider::$booted);
        $this->assertSame('registered', app('modules.provider_demo.flag'));
        $this->assertTrue(app('modules.provider_demo.service')->ready);
        $this->assertSame([StubExampleServiceProvider::class], $manager->registeredProviders('provider_demo'));
        $this->assertTrue($this->app->providerIsLoaded(StubExampleServiceProvider::class));
    }

    public function test_can_load_providers_without_module_interface_class(): void
    {
        $this->writeModule('provider_only', [
            'name' => 'provider_only',
            'version' => '1.0.0',
            'capabilities' => [],
            'providers' => [StubExampleServiceProvider::class],
        ]);

        app(ModuleManager::class)->load('provider_only');

        $this->assertTrue(StubExampleServiceProvider::$registered);
        $this->assertSame('registered', app('modules.provider_demo.flag'));
    }

    public function test_rejects_missing_provider_class(): void
    {
        $this->writeModule('missing_provider', [
            'name' => 'missing_provider',
            'version' => '1.0.0',
            'capabilities' => [],
            'providers' => ['Modules\\Ghost\\GhostServiceProvider'],
        ]);

        $this->expectException(ModuleBootstrapException::class);
        $this->expectExceptionMessage('declares provider');

        app(ModuleManager::class)->load('missing_provider');
    }

    public function test_rejects_non_service_provider_class(): void
    {
        $this->writeModule('invalid_provider', [
            'name' => 'invalid_provider',
            'version' => '1.0.0',
            'capabilities' => [],
            'providers' => [StubExampleModule::class],
        ]);

        $this->expectException(ModuleBootstrapException::class);
        $this->expectExceptionMessage('must extend Illuminate\\Support\\ServiceProvider');

        app(ModuleManager::class)->load('invalid_provider');
    }

    public function test_does_not_register_the_same_provider_twice_for_a_module(): void
    {
        $this->writeModule('provider_demo', [
            'name' => 'provider_demo',
            'version' => '1.0.0',
            'capabilities' => [],
            'providers' => [StubExampleServiceProvider::class],
        ]);

        $manager = app(ModuleManager::class);
        $manager->load('provider_demo');

        StubExampleServiceProvider::$registered = false;
        StubExampleServiceProvider::$booted = false;

        $manager->load('provider_demo');

        $this->assertFalse(StubExampleServiceProvider::$registered);
        $this->assertSame([StubExampleServiceProvider::class], $manager->registeredProviders('provider_demo'));
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
