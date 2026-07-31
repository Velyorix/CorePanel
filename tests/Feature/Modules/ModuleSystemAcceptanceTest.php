<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Core\Modules\Exceptions\ModuleBootstrapException;
use Core\Modules\Exceptions\ModuleSignatureException;
use Core\Modules\Models\InstalledModule;
use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModulePackageHasher;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Core\Modules\Services\ModuleSignatureVerifier;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Core\Providers\Services\ProviderRegistry;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\ExampleProvider\Server\ExampleServerProvider;
use Tests\Support\Modules\StubExampleServiceProvider;
use Tests\Support\Modules\StubSandboxModule;
use Tests\TestCase;

/**
 * End-to-end Feature coverage for the modules framework.
 */
class ModuleSystemAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->modulesPath = storage_path('framework/testing/modules-acceptance-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.version' => '1.2.0',
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.modules-acceptance',
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => true,
            'corepanel.modules.signature.secret' => 'acceptance-module-secret',
            'corepanel.modules.resources.routes' => true,
            'corepanel.modules.resources.views' => true,
            'corepanel.modules.resources.migrations' => true,
            'corepanel.themes.auto_load_active' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
        ]);

        $this->withoutVite();
        $this->rebindModuleManager($this->modulesPath);
        app(ProviderRegistry::class)->flush();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('module_sandbox_probe_settings');
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_example_module_install_enable_disable_without_error(): void
    {
        $this->rebindModuleManager(base_path('Modules'));

        $manager = app(ModuleManager::class);

        $manager->install('example');
        $this->assertDatabaseHas('installed_modules', [
            'name' => 'example',
            'enabled' => false,
            'version' => '1.0.0',
        ]);

        $manager->enable('example');
        $this->assertTrue($manager->isEnabled('example'));
        $this->assertTrue($manager->isLoaded('example'));
        $this->assertTrue(app(ProviderRegistry::class)->hasServer('example'));
        $this->assertInstanceOf(
            ExampleServerProvider::class,
            app(ProviderRegistry::class)->server('example'),
        );

        $manager->disable('example');
        $this->assertFalse($manager->isEnabled('example'));
        $this->assertFalse($manager->isLoaded('example'));
    }

    public function test_full_lifecycle_discover_install_enable_providers_disable_uninstall(): void
    {
        $this->writeModule('lifecycle', [
            'name' => 'lifecycle',
            'version' => '3.0.0',
            'label' => 'Lifecycle Demo',
            'capabilities' => ['server_provider'],
            'providers' => [StubExampleServiceProvider::class],
        ]);

        StubExampleServiceProvider::$registered = false;
        StubExampleServiceProvider::$booted = false;

        $manager = app(ModuleManager::class);

        $discovered = $manager->discover();
        $this->assertNotNull($discovered->firstWhere('key', 'lifecycle'));

        $manager->install('lifecycle');
        $this->assertDatabaseHas('installed_modules', [
            'name' => 'lifecycle',
            'enabled' => false,
            'version' => '3.0.0',
        ]);

        $manager->enable('lifecycle');
        $this->assertTrue($manager->isEnabled('lifecycle'));
        $this->assertTrue($manager->isLoaded('lifecycle'));
        $this->assertTrue(StubExampleServiceProvider::$registered);
        $this->assertTrue(StubExampleServiceProvider::$booted);
        $this->assertContains(
            StubExampleServiceProvider::class,
            $manager->registeredProviders('lifecycle'),
        );

        $manager->disable('lifecycle');
        $this->assertFalse($manager->isEnabled('lifecycle'));
        $this->assertFalse($manager->isLoaded('lifecycle'));

        $this->assertTrue($manager->uninstall('lifecycle'));
        $this->assertDatabaseMissing('installed_modules', ['name' => 'lifecycle']);
    }

    public function test_invalid_signature_blocks_module_installation(): void
    {
        $this->writeModule('signed', [
            'name' => 'signed',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        $manifest = app(ModuleManager::class)->get('signed');
        $this->assertNotNull($manifest);

        $checksum = app(ModulePackageHasher::class)->hash($manifest);
        $validSignature = app(ModuleSignatureVerifier::class)->sign($checksum);

        $this->writeModule('signed', [
            'name' => 'signed',
            'version' => '1.0.0',
            'capabilities' => [],
            'checksum' => $checksum,
            'signature' => str_replace(
                substr($validSignature, 0, 8),
                'deadbeef',
                $validSignature,
            ),
        ]);

        $this->rebindModuleManager($this->modulesPath);

        $this->expectException(ModuleSignatureException::class);
        $this->expectExceptionMessage('integrity verification');

        app(ModuleManager::class)->install('signed');
    }

    public function test_requirement_checker_rejects_incompatible_php_and_corepanel(): void
    {
        $this->writeModule('php_future', [
            'name' => 'php_future',
            'version' => '1.0.0',
            'capabilities' => [],
            'requires' => [
                'php' => '>=99.0.0',
            ],
        ]);

        try {
            app(ModuleManager::class)->load('php_future');
            $this->fail('Expected ModuleBootstrapException for PHP requirement.');
        } catch (ModuleBootstrapException $exception) {
            $this->assertStringContainsString('PHP >=99.0.0 required', $exception->getMessage());
        }

        $this->writeModule('cms_future', [
            'name' => 'cms_future',
            'version' => '1.0.0',
            'capabilities' => [],
            'requires' => [
                'corepanel' => '>=99.0.0',
            ],
        ]);

        $this->rebindModuleManager($this->modulesPath);

        $this->expectException(ModuleBootstrapException::class);
        $this->expectExceptionMessage('CorePanel >=99.0.0 required');

        app(ModuleManager::class)->load('cms_future');
    }

    public function test_admin_view_can_list_but_manage_required_for_mutations(): void
    {
        $this->writeModule('gated', [
            'name' => 'gated',
            'version' => '1.0.0',
            'label' => 'Gated Module',
            'capabilities' => [],
        ]);

        $viewer = $this->makeModulesViewer();

        $this->actingAs($viewer)
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('Gated Module');

        $this->actingAs($viewer)
            ->post(route('admin.modules.install', 'gated'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.modules.enable', 'gated'))
            ->assertForbidden();

        $this->assertDatabaseMissing('installed_modules', ['name' => 'gated']);
    }

    public function test_host_api_mediates_config_log_and_permissions(): void
    {
        $this->writeModule('sandbox_probe', [
            'name' => 'sandbox_probe',
            'version' => '1.0.0',
            'capabilities' => [],
            'module' => StubSandboxModule::class,
            'permissions' => [
                'module.sandbox_probe.panel.view',
            ],
        ]);

        $manager = app(ModuleManager::class);
        $manager->enable('sandbox_probe');

        /** @var StubSandboxModule $instance */
        $instance = $manager->instance('sandbox_probe');

        $this->assertSame('1.2.0', $instance->readAllowedConfig());
        $this->assertSame(1, $instance->permissionsRegistered);
        $this->assertDatabaseHas('permissions', [
            'name' => 'module.sandbox_probe.panel.view',
            'module' => 'sandbox_probe',
        ]);

        $instance->writeLog('acceptance host log');

        $this->assertSame('acceptance host log', $instance->lastLogMessage);
    }

    public function test_auto_load_enabled_loads_persisted_modules_from_boot_path(): void
    {
        $this->writeModule('autoload_me', [
            'name' => 'autoload_me',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        app(ModuleManager::class)->enable('autoload_me');
        $this->assertTrue(app(ModuleManager::class)->isLoaded('autoload_me'));

        config(['corepanel.modules.auto_load_enabled' => true]);
        $this->rebindModuleManager($this->modulesPath);

        $fresh = app(ModuleManager::class);
        $this->assertFalse($fresh->isLoaded('autoload_me'));

        if ((bool) config('corepanel.modules.auto_load_enabled', true)) {
            $loaded = $fresh->loadEnabled();
        } else {
            $loaded = collect();
        }

        $this->assertCount(1, $loaded);
        $this->assertTrue($fresh->isLoaded('autoload_me'));
        $this->assertTrue($fresh->isEnabled('autoload_me'));
    }

    public function test_tampered_checksum_blocks_load_after_install(): void
    {
        $this->writeModule('locked', [
            'name' => 'locked',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        $manifest = app(ModuleManager::class)->get('locked');
        $this->assertNotNull($manifest);

        $checksum = app(ModulePackageHasher::class)->hash($manifest);

        $this->writeModule('locked', [
            'name' => 'locked',
            'version' => '1.0.0',
            'capabilities' => [],
            'checksum' => $checksum,
        ]);

        $this->rebindModuleManager($this->modulesPath);

        app(ModuleManager::class)->install('locked');
        $this->assertNotNull(InstalledModule::query()->where('name', 'locked')->first());

        File::put($this->modulesPath.'/locked/tampered.txt', 'changed-after-install');

        $this->rebindModuleManager($this->modulesPath);

        $this->expectException(ModuleSignatureException::class);
        $this->expectExceptionMessage('integrity verification');

        app(ModuleManager::class)->load('locked');
    }

    private function makeModulesViewer(): User
    {
        $role = Role::query()->create([
            'name' => 'modules-viewer',
            'description' => 'Can view modules but not manage them',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', ['admin.access', 'modules.view'])
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function rebindModuleManager(string $modulesPath): void
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
            $modulesPath,
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
