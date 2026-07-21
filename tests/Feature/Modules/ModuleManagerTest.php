<?php

namespace Tests\Feature\Modules;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\InvalidModuleManifestException;
use Core\Modules\Exceptions\ModuleNotFoundException;
use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/modules-'.uniqid('', true));

        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
        ]);

        $this->rebindModuleManager();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_module_manager_is_registered_as_singleton(): void
    {
        $this->assertSame(app(ModuleManager::class), app(ModuleManager::class));
        $this->assertSame(app(InstalledModuleRepository::class), app(InstalledModuleRepository::class));
    }

    public function test_discovers_modules_with_valid_manifests(): void
    {
        $this->writeModule('alpha', [
            'name' => 'alpha',
            'version' => '1.0.0',
            'label' => 'Alpha Module',
            'capabilities' => ['server_provider'],
            'description' => 'Test alpha module',
        ]);

        $this->writeModule('beta', [
            'name' => 'beta',
            'version' => '2.1.0',
            'capabilities' => ['payment_gateway'],
        ]);

        File::ensureDirectoryExists($this->modulesPath.'/broken');
        File::put($this->modulesPath.'/broken/module.json', '{"name":"broken"}');

        File::ensureDirectoryExists($this->modulesPath.'/empty-dir');

        $discovered = app(ModuleManager::class)->discover();

        $this->assertCount(2, $discovered);
        $this->assertSame(['alpha', 'beta'], $discovered->pluck('key')->all());
        $this->assertSame('Alpha Module', $discovered->firstWhere('key', 'alpha')?->name);
        $this->assertSame(['server_provider'], $discovered->firstWhere('key', 'alpha')?->capabilities);
    }

    public function test_load_enable_and_disable_lifecycle(): void
    {
        $this->writeModule('gamma', [
            'name' => 'gamma',
            'version' => '1.2.3',
            'capabilities' => ['node_provider'],
        ]);

        $manager = app(ModuleManager::class);

        $loaded = $manager->load('gamma');
        $this->assertSame('gamma', $loaded->key);
        $this->assertTrue($manager->isLoaded('gamma'));
        $this->assertFalse($manager->isEnabled('gamma'));

        $manager->enable('gamma');
        $this->assertTrue($manager->isEnabled('gamma'));
        $this->assertTrue($manager->isLoaded('gamma'));
        $this->assertSame(['gamma'], app(InstalledModuleRepository::class)->enabledKeys());

        $manager->disable('gamma');
        $this->assertFalse($manager->isEnabled('gamma'));
        $this->assertFalse($manager->isLoaded('gamma'));
        $this->assertSame([], app(InstalledModuleRepository::class)->enabledKeys());
    }

    public function test_load_enabled_hydrates_runtime_registry_from_persisted_state(): void
    {
        $this->writeModule('delta', [
            'name' => 'delta',
            'version' => '0.1.0',
            'capabilities' => [],
        ]);

        $this->writeModule('epsilon', [
            'name' => 'epsilon',
            'version' => '0.2.0',
            'capabilities' => ['server_provider'],
        ]);

        app(ModuleManager::class)->enable('delta');

        $this->rebindModuleManager();

        $fresh = app(ModuleManager::class);
        $this->assertFalse($fresh->isLoaded('delta'));

        $loaded = $fresh->loadEnabled();

        $this->assertCount(1, $loaded);
        $this->assertTrue($fresh->isLoaded('delta'));
        $this->assertFalse($fresh->isLoaded('epsilon'));
        $this->assertSame(['delta'], $fresh->enabled()->pluck('key')->all());
    }

    public function test_load_unknown_module_throws(): void
    {
        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage('Module [missing] was not found');

        app(ModuleManager::class)->load('missing');
    }

    public function test_manifest_rejects_invalid_key(): void
    {
        $this->expectException(InvalidModuleManifestException::class);

        ModuleManifest::fromArray([
            'name' => 'Invalid Key',
            'version' => '1.0.0',
            'capabilities' => [],
        ], $this->modulesPath.'/invalid', 'invalid');
    }

    public function test_enable_persists_install_registry_row(): void
    {
        $this->writeModule('zeta', [
            'name' => 'zeta',
            'version' => '3.0.0',
            'capabilities' => ['payment_gateway'],
        ]);

        app(ModuleManager::class)->enable('zeta');

        $this->assertDatabaseHas('installed_modules', [
            'name' => 'zeta',
            'version' => '3.0.0',
            'enabled' => true,
        ]);
    }

    private function rebindModuleManager(): void
    {
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
            $this->modulesPath,
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
