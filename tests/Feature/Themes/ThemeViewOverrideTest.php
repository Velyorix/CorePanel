<?php

namespace Tests\Feature\Themes;

use Core\Themes\DataTransferObjects\ThemeDescriptor;
use Core\Themes\Exceptions\InvalidThemeManifestException;
use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeStateRepository;
use Core\Themes\Services\ThemeViewRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ThemeViewOverrideTest extends TestCase
{
    use RefreshDatabase;

    private string $themesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = storage_path('framework/testing/themes-'.uniqid('', true));
        File::ensureDirectoryExists($this->themesPath);

        config([
            'corepanel.themes.path' => $this->themesPath,
            'corepanel.themes.default' => 'default',
            'corepanel.themes.auto_load_active' => false,
            'corepanel.themes.override_module_views' => true,
            'session.driver' => 'array',
        ]);

        $this->rebindThemeServices();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themesPath);

        parent::tearDown();
    }

    public function test_manifest_parses_author_parent_and_custom_views_path(): void
    {
        $this->writeTheme('custom', [
            'name' => 'custom',
            'label' => 'Custom',
            'author' => 'Velyorix',
            'parent' => 'default',
            'views' => 'resources/views',
        ], views: [
            'sample.blade.php' => '<p>Custom</p>',
        ]);

        $descriptor = ThemeDescriptor::fromDirectory($this->themesPath.'/custom');

        $this->assertSame('custom', $descriptor->key);
        $this->assertSame('Velyorix', $descriptor->author);
        $this->assertSame('default', $descriptor->parent);
        $this->assertTrue($descriptor->hasViews());
    }

    public function test_core_view_override_uses_theme_template_when_present(): void
    {
        $this->writeTheme('branded', [
            'name' => 'branded',
            'label' => 'Branded',
        ], views: [
            'overrides/demo.blade.php' => '<p>Theme override</p>',
        ]);

        File::ensureDirectoryExists(resource_path('views/overrides'));
        File::put(resource_path('views/overrides/demo.blade.php'), '<p>Core fallback</p>');

        app(ThemeManager::class)->load('branded');

        $this->assertSame(
            '<p>Theme override</p>',
            trim(View::make('overrides.demo')->render()),
        );
    }

    public function test_core_view_falls_back_when_theme_has_no_override(): void
    {
        $this->writeTheme('branded', [
            'name' => 'branded',
            'label' => 'Branded',
        ], views: [
            'other.blade.php' => '<p>Theme only</p>',
        ]);

        File::ensureDirectoryExists(resource_path('views/overrides'));
        File::put(resource_path('views/overrides/demo.blade.php'), '<p>Core fallback</p>');

        app(ThemeManager::class)->load('branded');

        $this->assertSame(
            '<p>Core fallback</p>',
            trim(View::make('overrides.demo')->render()),
        );

        File::delete(resource_path('views/overrides/demo.blade.php'));
    }

    public function test_child_theme_overrides_parent_theme_override(): void
    {
        $this->writeTheme('default', [
            'name' => 'default',
            'label' => 'Default',
        ], views: [
            'overrides/demo.blade.php' => '<p>Parent override</p>',
        ]);

        $this->writeTheme('child', [
            'name' => 'child',
            'label' => 'Child',
            'parent' => 'default',
        ], views: [
            'overrides/demo.blade.php' => '<p>Child override</p>',
        ]);

        File::ensureDirectoryExists(resource_path('views/overrides'));
        File::put(resource_path('views/overrides/demo.blade.php'), '<p>Core fallback</p>');

        app(ThemeManager::class)->load('child');

        $this->assertSame(
            '<p>Child override</p>',
            trim(View::make('overrides.demo')->render()),
        );

        File::delete(resource_path('views/overrides/demo.blade.php'));
    }

    public function test_load_throws_for_unknown_parent_theme(): void
    {
        $this->writeTheme('orphan', [
            'name' => 'orphan',
            'parent' => 'missing',
        ]);

        $this->expectException(InvalidThemeManifestException::class);
        $this->expectExceptionMessage('unknown parent');

        app(ThemeManager::class)->load('orphan');
    }

    public function test_load_throws_for_circular_parent_chain(): void
    {
        $this->writeTheme('alpha', [
            'name' => 'alpha',
            'parent' => 'beta',
        ], withViews: false);

        $this->writeTheme('beta', [
            'name' => 'beta',
            'parent' => 'alpha',
        ], withViews: false);

        $this->expectException(InvalidThemeManifestException::class);
        $this->expectExceptionMessage('cycle');

        app(ThemeManager::class)->load('alpha');
    }

    public function test_clear_preview_restores_active_theme_view_overrides(): void
    {
        $this->writeTheme('default', [
            'name' => 'default',
        ], views: [
            'overrides/demo.blade.php' => '<p>Active theme</p>',
        ]);

        $this->writeTheme('preview', [
            'name' => 'preview',
        ], views: [
            'overrides/demo.blade.php' => '<p>Preview theme</p>',
        ]);

        File::ensureDirectoryExists(resource_path('views/overrides'));
        File::put(resource_path('views/overrides/demo.blade.php'), '<p>Core fallback</p>');

        $manager = app(ThemeManager::class);
        $manager->activate('default');

        $session = session()->driver();
        $manager->preview('preview', $session);

        $this->assertSame(
            '<p>Preview theme</p>',
            trim(View::make('overrides.demo')->render()),
        );

        $manager->clearPreview($session);

        $this->assertSame(
            '<p>Active theme</p>',
            trim(View::make('overrides.demo')->render()),
        );

        File::delete(resource_path('views/overrides/demo.blade.php'));
    }

    public function test_module_view_override_is_resolved_before_module_fallback(): void
    {
        $moduleKey = 'example';
        $moduleViewsPath = base_path('Modules/ExampleProvider/resources/views');
        $statusView = $moduleViewsPath.'/status.blade.php';
        $original = File::get($statusView);

        $this->writeTheme('branded', [
            'name' => 'branded',
        ], views: [
            'modules/'.$moduleKey.'/status.blade.php' => '<p>Theme module override</p>',
        ]);

        File::put($statusView, '<p>Module fallback</p>');

        try {
            app(\Core\Modules\Services\ModuleManager::class)->load('example');
            app(ThemeManager::class)->load('branded');

            $this->assertSame(
                '<p>Theme module override</p>',
                trim(View::make($moduleKey.'::status')->render()),
            );
        } finally {
            File::put($statusView, $original);
        }
    }

    public function test_resolve_inheritance_chain_returns_parent_first(): void
    {
        $this->writeTheme('default', ['name' => 'default'], withViews: false);
        $this->writeTheme('child', [
            'name' => 'child',
            'parent' => 'default',
        ], withViews: false);

        $manager = app(ThemeManager::class);
        $chain = $manager->resolveInheritanceChain($manager->findOrFail('child'));

        $this->assertSame(['default', 'child'], array_map(
            static fn (ThemeDescriptor $descriptor): string => $descriptor->key,
            $chain,
        ));
    }

    private function rebindThemeServices(): ThemeManager
    {
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetInstance(ThemeStateRepository::class);
        $this->app->forgetInstance(ThemeViewRegistrar::class);

        return app(ThemeManager::class);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $views
     */
    private function writeTheme(
        string $directory,
        array $manifest,
        array $views = [],
        bool $withViews = true,
    ): void {
        $path = $this->themesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/theme.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        if ($views !== []) {
            foreach ($views as $relativeView => $contents) {
                $viewPath = $path.'/resources/views/'.$relativeView;
                File::ensureDirectoryExists(dirname($viewPath));
                File::put($viewPath, $contents);
            }

            return;
        }

        if ($withViews) {
            File::ensureDirectoryExists($path.'/resources/views');
            File::put($path.'/resources/views/welcome.blade.php', '<p>Theme</p>');
        }
    }
}
