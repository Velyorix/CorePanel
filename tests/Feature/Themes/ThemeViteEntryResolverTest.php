<?php

namespace Tests\Feature\Themes;

use Core\Themes\DataTransferObjects\ThemeDescriptor;
use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeStateRepository;
use Core\Themes\Services\ThemeViteEntryResolver;
use Core\Themes\Services\ThemeViewRegistrar;
use Core\Themes\Support\ThemeAssetManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeViteEntryResolverTest extends TestCase
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
            'corepanel.themes.vite.core_entries' => [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            'session.driver' => 'array',
        ]);

        $this->rebindThemeServices();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themesPath);

        parent::tearDown();
    }

    public function test_resolve_returns_core_entries_when_no_theme_assets_exist(): void
    {
        $this->writeTheme('default', ['name' => 'default']);

        $entries = app(ThemeViteEntryResolver::class)->resolve();

        $this->assertSame([
            'resources/css/app.css',
            'resources/js/app.js',
        ], $entries);
    }

    public function test_manifest_asset_entries_are_parsed_and_resolved(): void
    {
        $this->writeTheme('branded', [
            'name' => 'branded',
            'assets' => [
                'entries' => [
                    'resources/css/theme.css',
                    'resources/js/theme.js',
                ],
            ],
        ], assets: [
            'resources/css/theme.css' => 'body {}',
            'resources/js/theme.js' => 'console.log("theme");',
        ]);

        app(ThemeManager::class)->activate('branded');

        $entries = app(ThemeViteEntryResolver::class)->resolve();

        $this->assertContains('resources/css/app.css', $entries);
        $this->assertContains('resources/js/app.js', $entries);
        $this->assertTrue(collect($entries)->contains(
            static fn (string $entry): bool => str_contains(str_replace('\\', '/', $entry), '/branded/resources/css/theme.css'),
        ));
    }

    public function test_convention_detects_theme_css_and_js_files(): void
    {
        $themeDir = $this->themesPath.'/classic';
        File::ensureDirectoryExists($themeDir);
        File::put($themeDir.'/theme.json', json_encode(['name' => 'classic']).PHP_EOL);
        File::ensureDirectoryExists($themeDir.'/resources/css');
        File::ensureDirectoryExists($themeDir.'/resources/js');
        File::put($themeDir.'/resources/css/theme.css', 'body {}');
        File::put($themeDir.'/resources/js/theme.js', 'void 0;');

        $descriptor = ThemeDescriptor::fromDirectory($themeDir);

        $this->assertSame([
            'resources/css/theme.css',
            'resources/js/theme.js',
        ], $descriptor->assetEntryPaths());
    }

    public function test_child_theme_assets_append_after_parent_entries(): void
    {
        $this->writeTheme('default', [
            'name' => 'default',
            'assets' => ['entries' => ['resources/css/theme.css']],
        ], assets: [
            'resources/css/theme.css' => '.default {}',
        ]);

        $this->writeTheme('child', [
            'name' => 'child',
            'parent' => 'default',
            'assets' => ['entries' => ['resources/css/theme.css']],
        ], assets: [
            'resources/css/theme.css' => '.child {}',
        ]);

        app(ThemeManager::class)->activate('child');

        $entries = app(ThemeViteEntryResolver::class)->resolve();
        $themeCssEntries = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => str_ends_with($entry, 'resources/css/theme.css'),
        ));

        $this->assertCount(2, $themeCssEntries);
        $this->assertStringContainsString('/default/', str_replace('\\', '/', $themeCssEntries[0]));
        $this->assertStringContainsString('/child/', str_replace('\\', '/', $themeCssEntries[1]));
    }

    public function test_all_build_entries_includes_every_discovered_theme_asset(): void
    {
        $this->writeTheme('alpha', [
            'name' => 'alpha',
            'assets' => ['entries' => ['resources/css/theme.css']],
        ], assets: [
            'resources/css/theme.css' => '.alpha {}',
        ]);

        $this->writeTheme('beta', [
            'name' => 'beta',
            'assets' => ['entries' => ['resources/js/theme.js']],
        ], assets: [
            'resources/js/theme.js' => 'void 0;',
        ]);

        $entries = app(ThemeViteEntryResolver::class)->allBuildEntries();

        $this->assertContains('resources/css/app.css', $entries);
        $this->assertContains('resources/js/app.js', $entries);
        $this->assertTrue(collect($entries)->contains(
            static fn (string $entry): bool => str_contains(str_replace('\\', '/', $entry), '/alpha/resources/css/theme.css'),
        ));
        $this->assertTrue(collect($entries)->contains(
            static fn (string $entry): bool => str_contains(str_replace('\\', '/', $entry), '/beta/resources/js/theme.js'),
        ));
    }

    public function test_theme_asset_manifest_skips_missing_declared_entries(): void
    {
        $themeDir = $this->themesPath.'/partial';
        File::ensureDirectoryExists($themeDir);
        File::put($themeDir.'/theme.json', json_encode([
            'name' => 'partial',
            'assets' => [
                'entries' => [
                    'resources/css/theme.css',
                    'resources/js/missing.js',
                ],
            ],
        ]).PHP_EOL);
        File::ensureDirectoryExists($themeDir.'/resources/css');
        File::put($themeDir.'/resources/css/theme.css', 'body {}');

        $entries = ThemeAssetManifest::entriesFromManifest(
            json_decode(File::get($themeDir.'/theme.json'), true),
            $themeDir,
        );

        $this->assertSame(['resources/css/theme.css'], $entries);
    }

    private function rebindThemeServices(): ThemeViteEntryResolver
    {
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetInstance(ThemeStateRepository::class);
        $this->app->forgetInstance(ThemeViewRegistrar::class);
        $this->app->forgetInstance(ThemeViteEntryResolver::class);

        return app(ThemeViteEntryResolver::class);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $assets
     */
    private function writeTheme(string $directory, array $manifest, array $assets = []): void
    {
        $path = $this->themesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/theme.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        foreach ($assets as $relativePath => $contents) {
            $assetPath = $path.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            File::ensureDirectoryExists(dirname($assetPath));
            File::put($assetPath, $contents);
        }
    }
}
