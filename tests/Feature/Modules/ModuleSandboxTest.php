<?php

namespace Tests\Feature\Modules;

use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Exceptions\ModuleSandboxViolationException;
use Core\Modules\Services\ModuleDatabaseGuard;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleStateRepository;
use Core\Modules\Services\ModuleTableAccessPolicy;
use Core\Providers\Services\ModulePermissionRegistrar;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Modules\StubCoreDbAccessModule;
use Tests\Support\Modules\StubSandboxModule;
use Tests\TestCase;

class ModuleSandboxTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->modulesPath = storage_path('framework/testing/modules-sandbox-'.uniqid('', true));
        $this->statePath = storage_path('framework/testing/module-state-sandbox-'.uniqid('', true).'.json');

        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.version' => '1.2.0',
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.state_path' => $this->statePath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.rbac.cache.enabled' => false,
            'corepanel.rbac.permissions_registry.cache_enabled' => false,
        ]);

        $this->app->forgetInstance(ModuleStateRepository::class);
        $this->app->forgetInstance(ModuleSandbox::class);
        $this->app->forgetInstance(ModuleTableAccessPolicy::class);
        $this->app->forgetInstance(ModuleDatabaseGuard::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleManager::class);

        $this->app->singleton(ModuleStateRepository::class, fn (): ModuleStateRepository => new ModuleStateRepository($this->statePath));
        $this->app->singleton(ModuleSandbox::class);
        $this->app->singleton(ModuleTableAccessPolicy::class);
        $this->app->singleton(ModuleDatabaseGuard::class);
        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(ModuleStateRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            app(ModuleSandbox::class),
            $this->modulesPath,
        ));

        app(ModuleDatabaseGuard::class)->register();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('module_sandbox_probe_settings');
        File::deleteDirectory($this->modulesPath);

        if (is_file($this->statePath)) {
            File::delete($this->statePath);
        }

        parent::tearDown();
    }

    public function test_sandbox_services_are_registered_as_singletons(): void
    {
        $this->assertSame(app(ModuleSandbox::class), app(ModuleSandbox::class));
        $this->assertSame(app(ModuleDatabaseGuard::class), app(ModuleDatabaseGuard::class));
        $this->assertSame(app(ModuleTableAccessPolicy::class), app(ModuleTableAccessPolicy::class));
    }

    public function test_module_cannot_query_core_tables_during_lifecycle(): void
    {
        $this->writeModule('evil', [
            'name' => 'evil',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::Other->value],
            'module' => StubCoreDbAccessModule::class,
        ]);

        $this->expectException(ModuleSandboxViolationException::class);
        $this->expectExceptionMessage('Core table [users]');

        app(ModuleManager::class)->load('evil');
    }

    public function test_module_can_use_own_prefixed_tables_and_host_api(): void
    {
        $this->writeModule('sandbox_probe', [
            'name' => 'sandbox_probe',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::Other->value],
            'module' => StubSandboxModule::class,
            'permissions' => [
                'module.sandbox_probe.panel.view',
            ],
        ]);

        $manager = app(ModuleManager::class);
        $manager->enable('sandbox_probe');

        /** @var StubSandboxModule $instance */
        $instance = $manager->instance('sandbox_probe');

        $this->assertTrue($instance->touchedOwnTable);
        $this->assertSame('1.2.0', $instance->coreVersion);
        $this->assertSame(1, $instance->permissionsRegistered);
        $this->assertDatabaseHas('module_sandbox_probe_settings', ['key' => 'enabled']);
        $this->assertDatabaseHas('permissions', [
            'name' => 'module.sandbox_probe.panel.view',
            'module' => 'sandbox_probe',
        ]);
    }

    public function test_core_code_can_query_core_tables_outside_sandbox(): void
    {
        $this->assertGreaterThan(0, DB::table('roles')->count());
        $this->assertFalse(app(ModuleSandbox::class)->isActive());
    }

    public function test_policy_rejects_foreign_module_tables(): void
    {
        $policy = app(ModuleTableAccessPolicy::class);

        $this->expectException(ModuleSandboxViolationException::class);
        $this->expectExceptionMessage('owned by another module');

        $policy->assertTableAllowed('alpha', 'module_beta_nodes');
    }

    public function test_host_api_blocks_sensitive_config_keys(): void
    {
        $this->writeModule('sandbox_probe', [
            'name' => 'sandbox_probe',
            'version' => '1.0.0',
            'capabilities' => [],
            'module' => StubSandboxModule::class,
        ]);

        $manager = app(ModuleManager::class);
        $manager->load('sandbox_probe');

        /** @var StubSandboxModule $instance */
        $instance = $manager->instance('sandbox_probe');

        $this->expectException(ModuleSandboxViolationException::class);
        $this->expectExceptionMessage('config key [database.default]');

        $instance->readForbiddenConfig();
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
