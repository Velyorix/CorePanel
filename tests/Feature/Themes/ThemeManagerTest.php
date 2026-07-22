<?php

namespace Tests\Feature\Themes;

use Core\Settings\Models\Setting;
use Core\Themes\DataTransferObjects\ThemeDescriptor;
use Core\Themes\Exceptions\ThemeNotFoundException;
use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeStateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ThemeManagerTest extends TestCase
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
            'session.driver' => 'array',
        ]);

        $this->rebindThemeManager();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themesPath);

        parent::tearDown();
    }

    public function test_theme_manager_is_registered_as_singleton(): void
    {
        $this->assertSame(app(ThemeManager::class), app(ThemeManager::class));
        $this->assertSame(app(ThemeStateRepository::class), app(ThemeStateRepository::class));
    }

    public function test_discovers_themes_with_valid_manifests(): void
    {
        $this->writeTheme('default', [
            'name' => 'default',
            'label' => 'Default Theme',
            'version' => '1.0.0',
        ]);

        $this->writeTheme('ocean', [
            'name' => 'ocean',
            'label' => 'Ocean Blue',
            'version' => '2.0.0',
        ]);

        File::ensureDirectoryExists($this->themesPath.'/broken');
        File::put($this->themesPath.'/broken/theme.json', '{bad json');

        $discovered = app(ThemeManager::class)->discover();

        $this->assertCount(2, $discovered);
        $this->assertSame(['default', 'ocean'], $discovered->pluck('key')->all());
        $this->assertSame('Ocean Blue', $discovered->firstWhere('key', 'ocean')?->label);
    }

    public function test_load_registers_view_namespace_when_views_exist(): void
    {
        $this->writeTheme('branded', [
            'name' => 'branded',
            'label' => 'Branded',
        ], withViews: true);

        $manager = app(ThemeManager::class);
        $descriptor = $manager->load('branded');

        $this->assertInstanceOf(ThemeDescriptor::class, $descriptor);
        $this->assertTrue($manager->isLoaded('branded'));
        $hints = View::getFinder()->getHints()['branded'] ?? [];
        $this->assertCount(1, $hints);
        $this->assertSame(
            str_replace('\\', '/', $this->themesPath.'/branded/resources/views'),
            str_replace('\\', '/', $hints[0]),
        );
    }

    public function test_activate_persists_and_loads_theme(): void
    {
        $this->writeTheme('default', [
            'name' => 'default',
            'label' => 'Default Theme',
        ]);

        $manager = app(ThemeManager::class);
        $descriptor = $manager->activate('default');

        $this->assertSame('default', $descriptor->key);
        $this->assertTrue($manager->isLoaded('default'));
        $this->assertSame('default', $manager->activeKey());
        $this->assertDatabaseHas('settings', [
            'key' => ThemeStateRepository::ACTIVE_KEY,
            'value' => 'default',
        ]);
    }

    public function test_preview_does_not_change_global_active_theme(): void
    {
        $this->writeTheme('default', ['name' => 'default', 'label' => 'Default']);
        $this->writeTheme('preview', ['name' => 'preview', 'label' => 'Preview Theme']);

        $manager = app(ThemeManager::class);
        $manager->activate('default');

        Setting::query()->where('key', ThemeStateRepository::ACTIVE_KEY)->update([
            'value' => 'default',
            'updated_at' => now(),
        ]);

        $session = session()->driver();
        $manager->preview('preview', $session);

        $this->assertSame('default', $manager->activeKey());
        $this->assertSame('preview', $manager->previewKey($session));
        $this->assertSame('preview', $manager->effectiveKey($session));
        $this->assertTrue($manager->isLoaded('preview'));
    }

    public function test_clear_preview_restores_effective_active_theme(): void
    {
        $this->writeTheme('default', ['name' => 'default']);
        $this->writeTheme('preview', ['name' => 'preview']);

        $manager = app(ThemeManager::class);
        $manager->activate('default');

        $session = session()->driver();
        $manager->preview('preview', $session);
        $manager->clearPreview($session);

        $this->assertNull($manager->previewKey($session));
        $this->assertSame('default', $manager->effectiveKey($session));
    }

    public function test_active_key_falls_back_to_configured_default(): void
    {
        $this->writeTheme('default', ['name' => 'default', 'label' => 'Default']);

        $manager = app(ThemeManager::class);

        $this->assertSame('default', $manager->activeKey());
    }

    public function test_find_or_fail_throws_for_unknown_theme(): void
    {
        $this->expectException(ThemeNotFoundException::class);

        app(ThemeManager::class)->findOrFail('missing');
    }

    public function test_load_active_loads_persisted_theme(): void
    {
        $this->writeTheme('default', ['name' => 'default']);
        $this->writeTheme('night', ['name' => 'night']);

        app(ThemeManager::class)->activate('night');

        $fresh = $this->rebindThemeManager();

        $loaded = $fresh->loadActive();

        $this->assertNotNull($loaded);
        $this->assertSame('night', $loaded->key);
        $this->assertTrue($fresh->isLoaded('night'));
    }

    private function rebindThemeManager(): ThemeManager
    {
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetInstance(ThemeStateRepository::class);
        $this->app->forgetInstance(\Core\Themes\Services\ThemeViewRegistrar::class);

        return app(ThemeManager::class);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeTheme(string $directory, array $manifest, bool $withViews = false): void
    {
        $path = $this->themesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/theme.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        if ($withViews) {
            File::ensureDirectoryExists($path.'/resources/views');
            File::put($path.'/resources/views/welcome.blade.php', '<p>Branded</p>');
        }
    }
}
