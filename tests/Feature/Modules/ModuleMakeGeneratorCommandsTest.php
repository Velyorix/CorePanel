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
}
