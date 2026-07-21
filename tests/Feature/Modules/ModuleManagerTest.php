<?php

namespace Tests\Feature\Modules;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\InvalidModuleManifestException;
use Core\Modules\Exceptions\ModuleNotFoundException;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleStateRepository;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleManagerTest extends TestCase
{
    private string $modulesPath;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/modules-'.uniqid('', true));
        $this->statePath = storage_path('framework/testing/module-state-'.uniqid('', true).'.json');

        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.state_path' => $this->statePath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
        ]);

        $this->app->forgetInstance(ModuleStateRepository::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleManager::class);

        $this->app->singleton(ModuleStateRepository::class, fn (): ModuleStateRepository => new ModuleStateRepository($this->statePath));
        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(ModuleStateRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            $this->modulesPath,
        ));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        if (is_file($this->statePath)) {
            File::delete($this->statePath);
        }

        parent::tearDown();
    }

    public function test_module_manager_is_registered_as_singleton(): void
    {
        $this->assertSame(app(ModuleManager::class), app(ModuleManager::class));
        $this->assertSame(app(ModuleStateRepository::class), app(ModuleStateRepository::class));
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
        $this->assertSame(['gamma'], app(ModuleStateRepository::class)->enabledKeys());

        $manager->disable('gamma');
        $this->assertFalse($manager->isEnabled('gamma'));
        $this->assertFalse($manager->isLoaded('gamma'));
        $this->assertSame([], app(ModuleStateRepository::class)->enabledKeys());
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

        $this->app->forgetInstance(ModuleManager::class);
        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(ModuleStateRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            $this->modulesPath,
        ));

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

    public function test_enable_persists_state_file(): void
    {
        $this->writeModule('zeta', [
            'name' => 'zeta',
            'version' => '3.0.0',
            'capabilities' => ['payment_gateway'],
        ]);

        app(ModuleManager::class)->enable('zeta');

        $this->assertFileExists($this->statePath);

        /** @var array{enabled?: list<string>} $payload */
        $payload = json_decode((string) file_get_contents($this->statePath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['zeta'], $payload['enabled'] ?? null);
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
