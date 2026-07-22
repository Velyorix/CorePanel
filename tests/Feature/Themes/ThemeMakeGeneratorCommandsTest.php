<?php

namespace Tests\Feature\Themes;

use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeScaffolder;
use Core\Themes\Services\ThemeStateRepository;
use Core\Themes\Services\ThemeViewRegistrar;
use Core\Themes\Services\ThemeViteEntryResolver;
use Core\Themes\Support\ThemeViewPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeMakeGeneratorCommandsTest extends TestCase
{
    use RefreshDatabase;

    private string $themesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = storage_path('framework/testing/themes-generators-'.uniqid('', true));
        File::ensureDirectoryExists($this->themesPath);

        config([
            'corepanel.themes.path' => $this->themesPath,
            'corepanel.themes.default' => 'demo',
            'corepanel.themes.auto_load_active' => false,
        ]);

        $this->rebindThemeServices();
        Artisan::call('theme:make', ['name' => 'Demo', '--key' => 'demo']);
        $this->rebindThemeServices();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themesPath);

        parent::tearDown();
    }

    public function test_theme_make_view_creates_blade_override(): void
    {
        $exitCode = Artisan::call('theme:make:view', [
            'name' => 'admin.clients.index',
            '--theme' => 'demo',
        ]);

        $this->assertSame(0, $exitCode);

        $path = $this->themesPath.'/Demo/resources/views/admin/clients/index.blade.php';
        $this->assertFileExists($path);
        $this->assertStringContainsString('Theme view override', File::get($path));
    }

    public function test_theme_make_layout_creates_layout_override(): void
    {
        Artisan::call('theme:make:layout', [
            'name' => 'admin',
            '--theme' => 'demo',
        ]);

        $path = $this->themesPath.'/Demo/resources/views/components/layout/admin.blade.php';
        $this->assertFileExists($path);
        $this->assertStringContainsString('Theme layout override', File::get($path));
    }

    public function test_theme_make_component_creates_component_override(): void
    {
        Artisan::call('theme:make:component', [
            'name' => 'ui.button',
            '--theme' => 'demo',
        ]);

        $path = $this->themesPath.'/Demo/resources/views/components/ui/button.blade.php';
        $this->assertFileExists($path);
        $this->assertStringContainsString('Theme component override', File::get($path));
    }

    public function test_theme_make_partial_creates_partial_override(): void
    {
        Artisan::call('theme:make:partial', [
            'name' => 'admin.partials.sidebar-shell',
            '--theme' => 'demo',
        ]);

        $path = $this->themesPath.'/Demo/resources/views/components/admin/partials/sidebar-shell.blade.php';
        $this->assertFileExists($path);
    }

    public function test_theme_make_asset_creates_file_and_updates_manifest(): void
    {
        Artisan::call('theme:make:asset', [
            'name' => 'branding',
            '--theme' => 'demo',
            '--type' => 'css',
        ]);

        $assetPath = $this->themesPath.'/Demo/resources/css/branding.css';
        $this->assertFileExists($assetPath);

        $manifest = json_decode(File::get($this->themesPath.'/Demo/theme.json'), true);
        $this->assertContains('resources/css/branding.css', $manifest['assets']['entries']);

        $entries = app(ThemeViteEntryResolver::class)->allBuildEntries();
        $this->assertTrue(collect($entries)->contains(
            static fn (string $entry): bool => str_contains(str_replace('\\', '/', $entry), '/Demo/resources/css/branding.css'),
        ));
    }

    public function test_generator_fails_when_target_file_already_exists(): void
    {
        Artisan::call('theme:make:view', [
            'name' => 'welcome',
            '--theme' => 'demo',
        ]);

        $exitCode = Artisan::call('theme:make:view', [
            'name' => 'welcome',
            '--theme' => 'demo',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', Artisan::output());
    }

    public function test_theme_view_path_helpers_normalize_dotted_names(): void
    {
        $this->assertSame(
            'admin/clients/index.blade.php',
            ThemeViewPath::normalize('admin.clients.index'),
        );
        $this->assertSame(
            'components/layout/admin.blade.php',
            ThemeViewPath::forLayout('admin'),
        );
        $this->assertSame(
            'components/ui/button.blade.php',
            ThemeViewPath::forComponent('ui.button'),
        );
        $this->assertSame(
            'components/partials/footer.blade.php',
            ThemeViewPath::forPartial('partials.footer'),
        );
    }

    private function rebindThemeServices(): void
    {
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetInstance(ThemeStateRepository::class);
        $this->app->forgetInstance(ThemeViewRegistrar::class);
        $this->app->forgetInstance(ThemeViteEntryResolver::class);
        $this->app->forgetInstance(ThemeScaffolder::class);
        $this->app->forgetInstance(\Core\Themes\Services\ThemeGenerator::class);
    }
}
