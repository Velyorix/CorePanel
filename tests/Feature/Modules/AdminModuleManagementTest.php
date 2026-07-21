<?php

namespace Tests\Feature\Modules;

use App\Models\User;
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
use Tests\TestCase;

class AdminModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->modulesPath = storage_path('framework/testing/modules-admin-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-modules',
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
            'corepanel.modules.signature.secret' => 'test-module-secret',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);

        $this->rebindModuleManager();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_admin_can_list_discovered_modules(): void
    {
        $this->writeModule('demo', [
            'name' => 'demo',
            'label' => 'Demo Module',
            'version' => '1.0.0',
            'description' => 'Example module for admin UI',
            'capabilities' => ['hooks'],
        ]);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('Demo Module')
            ->assertSee('demo')
            ->assertSee('1.0.0');
    }

    public function test_admin_can_install_enable_disable_and_configure_module(): void
    {
        $this->writeModule('demo', [
            'name' => 'demo',
            'label' => 'Demo Module',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.modules.install', 'demo'))
            ->assertRedirect(route('admin.modules.show', 'demo'));

        $this->assertDatabaseHas('installed_modules', [
            'name' => 'demo',
            'enabled' => false,
            'version' => '1.0.0',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.enable', 'demo'))
            ->assertRedirect(route('admin.modules.show', 'demo'));

        $this->assertDatabaseHas('installed_modules', [
            'name' => 'demo',
            'enabled' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.modules.config', 'demo'), [
                'config' => '{"feature":true,"limit":3}',
            ])
            ->assertRedirect(route('admin.modules.show', 'demo'));

        $this->assertSame(
            ['feature' => true, 'limit' => 3],
            app(ModuleManager::class)->installation('demo')?->config,
        );

        $this->actingAs($admin)
            ->post(route('admin.modules.disable', 'demo'))
            ->assertRedirect(route('admin.modules.show', 'demo'));

        $this->assertDatabaseHas('installed_modules', [
            'name' => 'demo',
            'enabled' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.modules.uninstall', 'demo'))
            ->assertRedirect(route('admin.modules.index'));

        $this->assertDatabaseMissing('installed_modules', ['name' => 'demo']);
    }

    public function test_module_show_page_renders_details(): void
    {
        $this->writeModule('demo', [
            'name' => 'demo',
            'label' => 'Demo Module',
            'version' => '2.1.0',
            'description' => 'Details page module',
            'capabilities' => ['hooks'],
        ]);

        app(ModuleManager::class)->install('demo');

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.modules.show', 'demo'))
            ->assertOk()
            ->assertSee('Demo Module')
            ->assertSee('Details page module')
            ->assertSee('2.1.0')
            ->assertSee(__('Save configuration'));
    }

    public function test_user_without_modules_view_cannot_access_index(): void
    {
        $this->writeModule('demo', [
            'name' => 'demo',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.modules.index'))
            ->assertForbidden();
    }

    public function test_invalid_config_json_is_rejected(): void
    {
        $this->writeModule('demo', [
            'name' => 'demo',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        app(ModuleManager::class)->install('demo');

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->from(route('admin.modules.show', 'demo'))
            ->put(route('admin.modules.config', 'demo'), [
                'config' => '{not-json',
            ])
            ->assertRedirect(route('admin.modules.show', 'demo'))
            ->assertSessionHasErrors('config');
    }

    public function test_navigation_links_modules_for_admin(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $item = collect(app(\Core\Admin\Navigation\AdminNavigation::class)->forUser($admin))
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Modules'));

        $this->assertNotNull($item);
        $this->assertFalse($item['placeholder']);
        $this->assertSame(route('admin.modules.index'), $item['url']);
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
