<?php

namespace Tests\Feature\Themes;

use App\Models\User;
use Core\Settings\Models\Setting;
use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeStateRepository;
use Core\Themes\Services\ThemeViewRegistrar;
use Core\Themes\Services\ThemeViteEntryResolver;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminThemeManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $themesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->themesPath = storage_path('framework/testing/themes-admin-'.uniqid('', true));
        File::ensureDirectoryExists($this->themesPath);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-themes',
            'corepanel.themes.path' => $this->themesPath,
            'corepanel.themes.default' => 'default',
            'corepanel.themes.auto_load_active' => false,
            'session.driver' => 'array',
        ]);

        $this->rebindThemeServices();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themesPath);

        parent::tearDown();
    }

    public function test_admin_can_list_discovered_themes(): void
    {
        $this->writeTheme('default', [
            'name' => 'default',
            'label' => 'Default Theme',
            'version' => '1.0.0',
            'author' => 'CorePanel',
        ]);

        $this->writeTheme('ocean', [
            'name' => 'ocean',
            'label' => 'Ocean Blue',
            'version' => '2.0.0',
        ]);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.themes.index'))
            ->assertOk()
            ->assertSee('Default Theme')
            ->assertSee('Ocean Blue')
            ->assertSee('default')
            ->assertSee('ocean');
    }

    public function test_admin_can_activate_theme_globally(): void
    {
        $this->writeTheme('default', ['name' => 'default', 'label' => 'Default Theme']);
        $this->writeTheme('night', ['name' => 'night', 'label' => 'Night Theme']);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.themes.activate', 'night'))
            ->assertRedirect(route('admin.themes.show', 'night'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('settings', [
            'key' => ThemeStateRepository::ACTIVE_KEY,
            'value' => 'night',
        ]);
    }

    public function test_admin_can_preview_theme_without_changing_global_active_theme(): void
    {
        $this->writeTheme('default', ['name' => 'default', 'label' => 'Default Theme']);
        $this->writeTheme('preview', ['name' => 'preview', 'label' => 'Preview Theme']);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.themes.activate', 'default'))
            ->assertRedirect(route('admin.themes.show', 'default'));

        $this->actingAs($admin)
            ->from(route('admin.themes.index'))
            ->post(route('admin.themes.preview', 'preview'))
            ->assertRedirect(route('admin.themes.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('settings', [
            'key' => ThemeStateRepository::ACTIVE_KEY,
            'value' => 'default',
        ]);

        $manager = app(ThemeManager::class);
        $this->assertSame('default', $manager->activeKey());
        $this->assertSame('preview', $manager->previewKey(session()->driver()));
    }

    public function test_admin_can_clear_theme_preview(): void
    {
        $this->writeTheme('default', ['name' => 'default']);
        $this->writeTheme('preview', ['name' => 'preview']);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)->post(route('admin.themes.activate', 'default'));
        $this->actingAs($admin)->post(route('admin.themes.preview', 'preview'));

        $this->actingAs($admin)
            ->from(route('admin.themes.index'))
            ->post(route('admin.themes.preview.clear'))
            ->assertRedirect(route('admin.themes.index'))
            ->assertSessionHas('status');

        $this->assertNull(app(ThemeManager::class)->previewKey(session()->driver()));
    }

    public function test_activate_clears_existing_preview_session(): void
    {
        $this->writeTheme('default', ['name' => 'default']);
        $this->writeTheme('night', ['name' => 'night']);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)->post(route('admin.themes.preview', 'default'));

        $this->actingAs($admin)
            ->post(route('admin.themes.activate', 'night'))
            ->assertRedirect(route('admin.themes.show', 'night'));

        $this->assertNull(app(ThemeManager::class)->previewKey(session()->driver()));
        $this->assertSame('night', Setting::query()->where('key', ThemeStateRepository::ACTIVE_KEY)->value('value'));
    }

    public function test_user_without_permission_cannot_manage_themes(): void
    {
        $this->writeTheme('default', ['name' => 'default', 'label' => 'Default Theme']);

        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.themes.index'))
            ->assertForbidden();
    }

    public function test_navigation_links_themes_for_admin(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $item = collect(app(\Core\Admin\Navigation\AdminNavigation::class)->forUser($admin))
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Themes'));

        $this->assertNotNull($item);
        $this->assertFalse($item['placeholder']);
        $this->assertSame(route('admin.themes.index'), $item['url']);
    }

    private function rebindThemeServices(): void
    {
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetInstance(ThemeStateRepository::class);
        $this->app->forgetInstance(ThemeViewRegistrar::class);
        $this->app->forgetInstance(ThemeViteEntryResolver::class);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeTheme(string $directory, array $manifest): void
    {
        $path = $this->themesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/theme.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }
}
