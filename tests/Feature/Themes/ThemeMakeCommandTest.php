<?php

namespace Tests\Feature\Themes;

use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeScaffolder;
use Core\Themes\Services\ThemeStateRepository;
use Core\Themes\Services\ThemeViewRegistrar;
use Core\Themes\Services\ThemeViteEntryResolver;
use Core\Themes\Support\ThemeName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeMakeCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $themesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = storage_path('framework/testing/themes-make-'.uniqid('', true));
        File::ensureDirectoryExists($this->themesPath);

        config([
            'corepanel.themes.path' => $this->themesPath,
            'corepanel.themes.default' => 'default',
            'corepanel.themes.auto_load_active' => false,
        ]);

        $this->rebindThemeServices();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themesPath);

        parent::tearDown();
    }

    public function test_theme_make_command_scaffolds_package_structure(): void
    {
        $exitCode = Artisan::call('theme:make', [
            'name' => 'Demo',
            '--author' => 'Velyorix',
            '--description' => 'Demo theme package',
        ]);

        $this->assertSame(0, $exitCode);

        $root = $this->themesPath.'/Demo';

        $this->assertDirectoryExists($root);
        $this->assertFileExists($root.'/theme.json');
        $this->assertFileExists($root.'/README.md');
        $this->assertDirectoryExists($root.'/resources/views');
        $this->assertFileExists($root.'/resources/css/theme.css');
        $this->assertFileExists($root.'/resources/js/theme.js');

        $manifest = json_decode(File::get($root.'/theme.json'), true);

        $this->assertSame('demo', $manifest['name']);
        $this->assertSame('Demo', $manifest['label']);
        $this->assertSame('1.0.0', $manifest['version']);
        $this->assertSame('Velyorix', $manifest['author']);
        $this->assertSame('Demo theme package', $manifest['description']);
        $this->assertSame([
            'resources/css/theme.css',
            'resources/js/theme.js',
        ], $manifest['assets']['entries']);

        $this->assertTrue(app(ThemeManager::class)->has('demo'));
    }

    public function test_theme_make_supports_custom_key_and_label(): void
    {
        Artisan::call('theme:make', [
            'name' => 'Ocean Blue',
            '--key' => 'ocean-pro',
            '--label' => 'Ocean Pro',
        ]);

        $root = $this->themesPath.'/OceanBlue';

        $this->assertDirectoryExists($root);

        $manifest = json_decode(File::get($root.'/theme.json'), true);

        $this->assertSame('ocean-pro', $manifest['name']);
        $this->assertSame('Ocean Pro', $manifest['label']);
    }

    public function test_theme_make_fails_when_directory_already_exists(): void
    {
        File::ensureDirectoryExists($this->themesPath.'/Demo');

        $exitCode = Artisan::call('theme:make', ['name' => 'Demo']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', Artisan::output());
    }

    public function test_theme_make_fails_when_key_already_registered(): void
    {
        Artisan::call('theme:make', ['name' => 'Demo']);

        $exitCode = Artisan::call('theme:make', [
            'name' => 'Demo Clone',
            '--key' => 'demo',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already registered', Artisan::output());
    }

    public function test_theme_name_parser_normalizes_directory_and_key(): void
    {
        $parsed = ThemeName::fromInput('dark-pro');

        $this->assertSame('DarkPro', $parsed->directory);
        $this->assertSame('dark-pro', $parsed->key);
        $this->assertSame('Dark Pro', $parsed->label);
    }

    public function test_scaffolder_registers_theme_in_vite_entry_resolver(): void
    {
        Artisan::call('theme:make', ['name' => 'Branded']);

        $entries = app(ThemeViteEntryResolver::class)->allBuildEntries();

        $this->assertTrue(collect($entries)->contains(
            static fn (string $entry): bool => str_contains(str_replace('\\', '/', $entry), '/Branded/resources/css/theme.css'),
        ));
    }

    private function rebindThemeServices(): void
    {
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetInstance(ThemeStateRepository::class);
        $this->app->forgetInstance(ThemeViewRegistrar::class);
        $this->app->forgetInstance(ThemeViteEntryResolver::class);
        $this->app->forgetInstance(ThemeScaffolder::class);
    }
}
