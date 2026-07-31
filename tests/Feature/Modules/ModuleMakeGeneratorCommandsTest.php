<?php

namespace Tests\Feature\Modules;

use Core\Modules\Services\ModuleGenerator;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModuleScaffolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleMakeGeneratorCommandsTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/modules-generators-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
        ]);

        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(ModuleScaffolder::class);
        $this->app->forgetInstance(ModuleGenerator::class);

        Artisan::call('module:make', [
            'name' => 'Generator Demo',
            '--key' => 'generator_demo',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_make_migration_creates_prefixed_table_migration(): void
    {
        $exitCode = Artisan::call('module:make:migration', [
            'name' => 'create_items_table',
            '--module' => 'generator_demo',
        ]);

        $this->assertSame(0, $exitCode);

        $files = File::glob($this->modulesPath.'/GeneratorDemo/database/migrations/*_create_module_generator_demo_items_table.php');

        $this->assertNotEmpty($files);
        $this->assertStringContainsString("Schema::create('module_generator_demo_items'", File::get($files[0]));
    }

    public function test_make_model_creates_prefixed_eloquent_model(): void
    {
        $exitCode = Artisan::call('module:make:model', [
            'name' => 'Item',
            '--module' => 'generator_demo',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Models/Item.php');

        $contents = File::get($this->modulesPath.'/GeneratorDemo/Models/Item.php');

        $this->assertStringContainsString("protected \$table = 'module_generator_demo_item';", $contents);
    }

    public function test_make_controller_service_and_event_commands_create_files(): void
    {
        $this->assertSame(0, Artisan::call('module:make:controller', [
            'name' => 'Status',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Http/Controllers/StatusController.php');

        $this->assertSame(0, Artisan::call('module:make:service', [
            'name' => 'Status',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Services/StatusService.php');

        $this->assertSame(0, Artisan::call('module:make:event', [
            'name' => 'StatusChecked',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Events/StatusChecked.php');
    }

    public function test_generator_commands_require_module_option(): void
    {
        $exitCode = Artisan::call('module:make:model', ['name' => 'Ghost']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('--module=', Artisan::output());
    }

    public function test_make_model_with_migration_creates_both_files(): void
    {
        $exitCode = Artisan::call('module:make:model', [
            'name' => 'Widget',
            '--module' => 'generator_demo',
            '--migration' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Models/Widget.php');

        $files = File::glob($this->modulesPath.'/GeneratorDemo/database/migrations/*_create_module_generator_demo_widget_table.php');
        $this->assertNotEmpty($files);
    }

    public function test_make_controller_with_admin_scope_appends_route(): void
    {
        $exitCode = Artisan::call('module:make:controller', [
            'name' => 'Dashboard',
            '--module' => 'generator_demo',
            '--admin' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $routes = File::get($this->modulesPath.'/GeneratorDemo/routes/admin.php');
        $this->assertStringContainsString('DashboardController::class', $routes);
        $this->assertStringContainsString("->name('module.generator_demo.dashboard')", $routes);
    }

    public function test_extended_generators_create_files_and_update_manifest(): void
    {
        $this->assertSame(0, Artisan::call('module:make:factory', [
            'name' => 'Item',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Database/Factories/ItemFactory.php');

        $this->assertSame(0, Artisan::call('module:make:seeder', [
            'name' => 'Item',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Database/Seeders/ItemSeeder.php');

        $this->assertSame(0, Artisan::call('module:make:listener', [
            'name' => 'NotifyStatus',
            '--module' => 'generator_demo',
            '--event' => 'StatusChecked',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Listeners/NotifyStatusListener.php');

        $this->assertSame(0, Artisan::call('module:make:trait', [
            'name' => 'Cacheable',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Traits/CacheableTrait.php');

        $this->assertSame(0, Artisan::call('module:make:request', [
            'name' => 'StoreItem',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Http/Requests/StoreItemRequest.php');

        $this->assertSame(0, Artisan::call('module:make:command', [
            'name' => 'Sync',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Console/Commands/SyncCommand.php');

        $this->assertSame(0, Artisan::call('module:make:provider', [
            'name' => 'Extra',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Providers/ExtraServiceProvider.php');

        $manifest = json_decode(File::get($this->modulesPath.'/GeneratorDemo/module.json'), true);
        $this->assertContains('Modules\\GeneratorDemo\\Providers\\ExtraServiceProvider', $manifest['providers']);

        $this->assertSame(0, Artisan::call('module:make:gateway', [
            'name' => 'Acme',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Gateways/AcmeGateway.php');

        $manifest = json_decode(File::get($this->modulesPath.'/GeneratorDemo/module.json'), true);
        $this->assertContains('Modules\\GeneratorDemo\\Gateways\\AcmeGateway', $manifest['gateways']);

        $this->assertSame(0, Artisan::call('module:make:policy', [
            'name' => 'Item',
            '--module' => 'generator_demo',
        ]));
        $this->assertFileExists($this->modulesPath.'/GeneratorDemo/Policies/ItemPolicy.php');

        $manifest = json_decode(File::get($this->modulesPath.'/GeneratorDemo/module.json'), true);
        $permissionNames = array_map(
            fn (mixed $entry): string => is_string($entry) ? $entry : ($entry['name'] ?? ''),
            $manifest['permissions'] ?? [],
        );
        $this->assertContains('module.generator_demo.items.manage', $permissionNames);
    }
}
