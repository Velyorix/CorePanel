<?php

namespace Tests\Feature\Modules;

use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Core\Modules\Services\ModuleStateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModuleResourceLoaderTest extends TestCase
{
    use RefreshDatabase;
    private string $modulesPath;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/modules-resources-'.uniqid('', true));
        $this->statePath = storage_path('framework/testing/module-state-resources-'.uniqid('', true).'.json');

        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.state_path' => $this->statePath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.resources.routes' => true,
            'corepanel.modules.resources.views' => true,
            'corepanel.modules.resources.migrations' => true,
        ]);

        $this->rebindModuleManager();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('module_resource_demo_widgets');
        File::deleteDirectory($this->modulesPath);

        if (is_file($this->statePath)) {
            File::delete($this->statePath);
        }

        parent::tearDown();
    }

    public function test_resource_loader_is_registered_as_singleton(): void
    {
        $this->assertSame(app(ModuleResourceLoader::class), app(ModuleResourceLoader::class));
    }

    public function test_auto_loads_routes_views_and_migrations(): void
    {
        $this->seedResourceDemoModule();

        $manager = app(ModuleManager::class);
        $manager->load('resource_demo');

        $resources = $manager->loadedResources('resource_demo');

        $this->assertNotNull($resources);
        $this->assertSame(['web.php'], $resources->routeFiles);
        $this->assertTrue($resources->views);
        $this->assertTrue($resources->migrations);
        $this->assertSame('resource_demo', $resources->viewsNamespace);
        $this->assertNotNull($resources->migrationsPath);

        $this->assertTrue(Route::has('module.resource_demo.ping'));
        $this->assertSame(200, $this->get('/__module-resource-demo/ping')->getStatusCode());
        $this->assertTrue(view()->exists('resource_demo::hello'));
        $this->assertStringContainsString(
            'Hello from module',
            view('resource_demo::hello')->render(),
        );

        $this->assertContains(
            $resources->migrationsPath,
            app('migrator')->paths(),
        );

        Artisan::call('migrate', ['--force' => true]);

        $this->assertTrue(Schema::hasTable('module_resource_demo_widgets'));
    }

    public function test_can_disable_individual_resource_types(): void
    {
        config([
            'corepanel.modules.resources.routes' => false,
            'corepanel.modules.resources.views' => true,
            'corepanel.modules.resources.migrations' => false,
        ]);

        $this->rebindModuleManager();
        $this->seedResourceDemoModule();

        $resources = app(ModuleManager::class)->load('resource_demo');
        $loaded = app(ModuleManager::class)->loadedResources('resource_demo');

        $this->assertSame('resource_demo', $resources->key);
        $this->assertSame([], $loaded?->routeFiles);
        $this->assertTrue((bool) $loaded?->views);
        $this->assertFalse((bool) $loaded?->migrations);
        $this->assertFalse(Route::has('module.resource_demo.ping'));
    }

    public function test_forget_removes_view_namespace_tracking(): void
    {
        $this->seedResourceDemoModule();

        $manager = app(ModuleManager::class);
        $manager->load('resource_demo');
        $this->assertTrue(view()->exists('resource_demo::hello'));

        $manager->disable('resource_demo');

        $this->assertNull($manager->loadedResources('resource_demo'));
    }

    private function rebindModuleManager(): void
    {
        $this->app->forgetInstance(ModuleStateRepository::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleServiceProviderRegistrar::class);
        $this->app->forgetInstance(ModuleResourceLoader::class);
        $this->app->forgetInstance(ModuleManager::class);

        $this->app->singleton(ModuleStateRepository::class, fn (): ModuleStateRepository => new ModuleStateRepository($this->statePath));
        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(ModuleStateRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            app(ModuleSandbox::class),
            app(ModuleServiceProviderRegistrar::class),
            app(ModuleResourceLoader::class),
            $this->modulesPath,
        ));
    }

    private function seedResourceDemoModule(): void
    {
        $root = $this->modulesPath.'/resource_demo';

        File::ensureDirectoryExists($root.'/routes');
        File::ensureDirectoryExists($root.'/resources/views');
        File::ensureDirectoryExists($root.'/database/migrations');

        File::put($root.'/module.json', json_encode([
            'name' => 'resource_demo',
            'version' => '1.0.0',
            'capabilities' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        File::put($root.'/routes/web.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/__module-resource-demo/ping', fn () => response('pong'))->name('ping');
PHP);

        File::put($root.'/resources/views/hello.blade.php', 'Hello from module');

        File::put(
            $root.'/database/migrations/2026_07_21_000001_create_module_resource_demo_widgets_table.php',
            <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_resource_demo_widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_resource_demo_widgets');
    }
};
PHP
        );
    }
}
