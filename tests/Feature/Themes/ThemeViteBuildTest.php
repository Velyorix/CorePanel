<?php

namespace Tests\Feature\Themes;

use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeStateRepository;
use Core\Themes\Services\ThemeViewRegistrar;
use Core\Themes\Services\ThemeViteBuilder;
use Core\Themes\Services\ThemeViteEntryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ThemeViteBuildTest extends TestCase
{
    use RefreshDatabase;

    private string $themesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = storage_path('framework/testing/themes-vite-build-'.uniqid('', true));
        File::ensureDirectoryExists($this->themesPath);

        config([
            'corepanel.themes.path' => $this->themesPath,
            'corepanel.themes.default' => 'default',
            'corepanel.themes.auto_load_active' => false,
            'corepanel.themes.vite.binary' => 'npx',
            'corepanel.themes.vite.package' => 'vite',
            'corepanel.themes.vite.core_entries' => [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
        ]);

        $this->rebindThemeServices();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themesPath);

        parent::tearDown();
    }

    public function test_theme_build_runs_vite_for_all_discovered_themes(): void
    {
        $this->writeTheme('alpha', [
            'name' => 'alpha',
            'assets' => ['entries' => ['resources/css/theme.css']],
        ], assets: [
            'resources/css/theme.css' => '.alpha {}',
        ]);

        Process::fake([
            '*' => Process::result(exitCode: 0),
        ]);

        $exitCode = Artisan::call('theme:build');

        $this->assertSame(0, $exitCode);

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === ['npx', 'vite', 'build']
                && $this->normalizePath($process->environment['COREPANEL_THEMES_PATH'] ?? '') === $this->normalizePath($this->themesPath)
                && ! array_key_exists('COREPANEL_VITE_INPUTS', $process->environment);
        });
    }

    public function test_theme_build_scopes_vite_inputs_to_requested_theme(): void
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

        Process::fake([
            '*' => Process::result(exitCode: 0),
        ]);

        $exitCode = Artisan::call('theme:build', ['theme' => 'alpha']);

        $this->assertSame(0, $exitCode);

        Process::assertRan(function (PendingProcess $process): bool {
            $inputs = $process->environment['COREPANEL_VITE_INPUTS'] ?? '';

            return $process->command === ['npx', 'vite', 'build']
                && str_contains($inputs, '/alpha/resources/css/theme.css')
                && ! str_contains($inputs, '/beta/');
        });
    }

    public function test_theme_build_fails_for_unknown_theme(): void
    {
        Process::fake();

        $exitCode = Artisan::call('theme:build', ['theme' => 'missing']);

        $this->assertSame(1, $exitCode);
        Process::assertNothingRan();
    }

    public function test_theme_watch_starts_vite_dev_server(): void
    {
        $this->writeTheme('alpha', [
            'name' => 'alpha',
            'assets' => ['entries' => ['resources/css/theme.css']],
        ], assets: [
            'resources/css/theme.css' => '.alpha {}',
        ]);

        Process::fake([
            '*' => Process::result(exitCode: 0),
        ]);

        $exitCode = Artisan::call('theme:watch', ['theme' => 'alpha']);

        $this->assertSame(0, $exitCode);

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === ['npx', 'vite']
                && isset($process->environment['COREPANEL_VITE_INPUTS']);
        });
    }

    public function test_theme_build_surfaces_vite_process_failures(): void
    {
        $this->writeTheme('alpha', [
            'name' => 'alpha',
            'assets' => ['entries' => ['resources/css/theme.css']],
        ], assets: [
            'resources/css/theme.css' => '.alpha {}',
        ]);

        Process::fake([
            '*' => Process::result(exitCode: 1, errorOutput: 'Build failed'),
        ]);

        $exitCode = Artisan::call('theme:build', ['theme' => 'alpha']);

        $this->assertSame(1, $exitCode);
    }

    public function test_build_entries_include_theme_inheritance_chain(): void
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
            'assets' => ['entries' => ['resources/js/theme.js']],
        ], assets: [
            'resources/js/theme.js' => 'void 0;',
        ]);

        $entries = app(ThemeViteEntryResolver::class)->buildEntries('child');

        $this->assertContains('resources/css/app.css', $entries);
        $this->assertContains('resources/js/app.js', $entries);
        $this->assertTrue(collect($entries)->contains(
            static fn (string $entry): bool => str_contains(str_replace('\\', '/', $entry), '/default/resources/css/theme.css'),
        ));
        $this->assertTrue(collect($entries)->contains(
            static fn (string $entry): bool => str_contains(str_replace('\\', '/', $entry), '/child/resources/js/theme.js'),
        ));
        $this->assertFalse(collect($entries)->contains(
            static fn (string $entry): bool => str_contains(str_replace('\\', '/', $entry), '/beta/'),
        ));
    }

    public function test_preview_entries_delegates_to_resolver(): void
    {
        $this->writeTheme('alpha', [
            'name' => 'alpha',
            'assets' => ['entries' => ['resources/css/theme.css']],
        ], assets: [
            'resources/css/theme.css' => '.alpha {}',
        ]);

        $entries = app(ThemeViteBuilder::class)->previewEntries('alpha');

        $this->assertSame(
            app(ThemeViteEntryResolver::class)->buildEntries('alpha'),
            $entries,
        );
    }

    private function rebindThemeServices(): void
    {
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetInstance(ThemeStateRepository::class);
        $this->app->forgetInstance(ThemeViewRegistrar::class);
        $this->app->forgetInstance(ThemeViteEntryResolver::class);
        $this->app->forgetInstance(ThemeViteBuilder::class);
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
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
