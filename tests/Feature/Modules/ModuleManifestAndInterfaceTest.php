<?php

namespace Tests\Feature\Modules;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Exceptions\InvalidModuleManifestException;
use Core\Modules\Exceptions\ModuleBootstrapException;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleStateRepository;
use Illuminate\Support\Facades\File;
use Tests\Support\Modules\StubExampleModule;
use Tests\TestCase;

class ModuleManifestAndInterfaceTest extends TestCase
{
    private string $modulesPath;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/modules-schema-'.uniqid('', true));
        $this->statePath = storage_path('framework/testing/module-state-schema-'.uniqid('', true).'.json');

        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.version' => '1.2.0',
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
            app(ModuleSandbox::class),
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

    public function test_parses_full_module_json_schema(): void
    {
        $manifest = ModuleManifest::fromArray([
            'name' => 'example',
            'version' => '1.0.0',
            'label' => 'Example Provider',
            'description' => 'Demo integration module',
            'capabilities' => [ModuleCapability::ServerProvider->value, 'custom_hook'],
            'module' => StubExampleModule::class,
            'providers' => ['Modules\\Example\\Providers\\ExampleServiceProvider'],
            'authors' => [
                ['name' => 'Velyorix', 'email' => 'dev@example.test'],
                'Jane Doe',
            ],
            'homepage' => 'https://example.test/modules/example',
            'license' => 'MIT',
            'requires' => [
                'corepanel' => '>=1.0.0',
                'php' => '>=8.4',
            ],
            'permissions' => [
                'module.example.server.create',
                [
                    'name' => 'module.example.server.restart',
                    'description' => 'Restart servers',
                ],
            ],
        ], $this->modulesPath.'/example', 'example');

        $this->assertSame('example', $manifest->key);
        $this->assertSame('Example Provider', $manifest->name);
        $this->assertSame('1.0.0', $manifest->version);
        $this->assertTrue($manifest->hasCapability(ModuleCapability::ServerProvider->value));
        $this->assertSame(StubExampleModule::class, $manifest->moduleClass);
        $this->assertSame(['Modules\\Example\\Providers\\ExampleServiceProvider'], $manifest->providers);
        $this->assertCount(2, $manifest->authors);
        $this->assertSame('Velyorix', $manifest->authors[0]->name);
        $this->assertSame('>=1.0.0', $manifest->requires->corepanel);
        $this->assertSame([
            'module.example.server.create',
            'module.example.server.restart',
        ], $manifest->permissionManifest()->permissionNames());
    }

    public function test_rejects_invalid_semver_and_missing_capabilities(): void
    {
        try {
            ModuleManifest::fromArray([
                'name' => 'bad-version',
                'version' => 'v1',
                'capabilities' => [],
            ], $this->modulesPath.'/bad', 'bad');
            $this->fail('Expected invalid version exception.');
        } catch (InvalidModuleManifestException $exception) {
            $this->assertStringContainsString('semver', $exception->getMessage());
        }

        try {
            ModuleManifest::fromArray([
                'name' => 'no-caps',
                'version' => '1.0.0',
            ], $this->modulesPath.'/nocaps', 'nocaps');
            $this->fail('Expected missing capabilities exception.');
        } catch (InvalidModuleManifestException $exception) {
            $this->assertStringContainsString('capabilities', $exception->getMessage());
        }
    }

    public function test_loads_module_interface_and_runs_lifecycle_hooks(): void
    {
        $this->writeModule('lifecycle', [
            'name' => 'lifecycle',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::Other->value],
            'module' => StubExampleModule::class,
            'requires' => [
                'corepanel' => '>=1.0.0',
                'php' => '>=8.4',
            ],
        ]);

        $manager = app(ModuleManager::class);
        $manager->enable('lifecycle');

        $instance = $manager->instance('lifecycle');

        $this->assertInstanceOf(StubExampleModule::class, $instance);
        $this->assertTrue($instance->registered);
        $this->assertTrue($instance->booted);
        $this->assertTrue($instance->enabled);

        $manager->disable('lifecycle');

        $this->assertTrue($instance->disabled);
        $this->assertNull($manager->instance('lifecycle'));
    }

    public function test_rejects_unsatisfied_corepanel_requirement_on_load(): void
    {
        $this->writeModule('future', [
            'name' => 'future',
            'version' => '1.0.0',
            'capabilities' => [],
            'requires' => [
                'corepanel' => '>=99.0.0',
            ],
        ]);

        $this->expectException(ModuleBootstrapException::class);
        $this->expectExceptionMessage('CorePanel >=99.0.0 required');

        app(ModuleManager::class)->load('future');
    }

    public function test_rejects_missing_module_class_on_load(): void
    {
        $this->writeModule('ghost', [
            'name' => 'ghost',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::ServerProvider->value],
            'module' => 'Modules\\Ghost\\GhostModule',
        ]);

        $this->expectException(ModuleBootstrapException::class);
        $this->expectExceptionMessage('could not be found');

        app(ModuleManager::class)->load('ghost');
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
