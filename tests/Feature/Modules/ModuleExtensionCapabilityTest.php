<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Enums\ModuleProfile;
use Core\Modules\Exceptions\InvalidModuleManifestException;
use Core\Modules\Services\ModuleManager;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleExtensionCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/modules-extension-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
            'corepanel.themes.auto_load_active' => false,
        ]);

        $this->app->forgetInstance(ModuleManager::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_parses_extension_module_with_hook_manifest(): void
    {
        $manifest = $this->manifestFromFile([
            'name' => 'discord_notify',
            'version' => '1.0.0',
            'capabilities' => [
                ModuleCapability::Extension->value,
                ModuleCapability::NotificationChannel->value,
            ],
            'hooks' => [
                'events' => ['invoice.paid', 'order.paid'],
                'hooks' => ['admin.navigation.build'],
                'filters' => ['invoice.email.subject'],
            ],
        ]);

        $this->assertTrue($manifest->isExtension());
        $this->assertFalse($manifest->isIntegration());
        $this->assertSame(ModuleProfile::Extension, $manifest->profile());
        $this->assertSame(['extension', 'notification_channel'], $manifest->extensionCapabilities());
        $this->assertSame([], $manifest->integrationCapabilities());
        $this->assertSame(['invoice.paid', 'order.paid'], $manifest->hookManifest->events);
        $this->assertSame(['admin.navigation.build'], $manifest->hookManifest->hooks);
        $this->assertSame(['invoice.email.subject'], $manifest->hookManifest->filters);
    }

    public function test_hybrid_profile_when_extension_and_integration_capabilities_coexist(): void
    {
        $manifest = $this->manifestFromFile([
            'name' => 'hybrid',
            'version' => '1.0.0',
            'capabilities' => [
                ModuleCapability::Extension->value,
                ModuleCapability::PaymentGateway->value,
            ],
        ]);

        $this->assertTrue($manifest->isExtension());
        $this->assertTrue($manifest->isIntegration());
        $this->assertSame(ModuleProfile::Hybrid, $manifest->profile());
        $this->assertSame(['payment_gateway'], $manifest->integrationCapabilities());
    }

    public function test_rejects_notification_channel_without_extension_capability(): void
    {
        $this->expectException(InvalidModuleManifestException::class);
        $this->expectExceptionMessage('notification_channel');

        $this->manifestFromFile([
            'name' => 'notify_only',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::NotificationChannel->value],
        ]);
    }

    public function test_rejects_hook_manifest_without_extension_capability(): void
    {
        $this->expectException(InvalidModuleManifestException::class);
        $this->expectExceptionMessage('[hooks]');

        $this->manifestFromFile([
            'name' => 'invalid_hooks',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::Other->value],
            'hooks' => [
                'events' => ['invoice.paid'],
            ],
        ]);
    }

    public function test_rejects_invalid_hook_name_format(): void
    {
        $this->expectException(InvalidModuleManifestException::class);
        $this->expectExceptionMessage('hooks.events');

        $this->manifestFromFile([
            'name' => 'bad_hook_name',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::Extension->value],
            'hooks' => [
                'events' => ['Invoice.Paid'],
            ],
        ]);
    }

    public function test_allows_custom_capabilities_alongside_extension(): void
    {
        $manifest = $this->manifestFromFile([
            'name' => 'custom',
            'version' => '1.0.0',
            'capabilities' => [
                ModuleCapability::Extension->value,
                'custom_hook',
            ],
        ]);

        $this->assertTrue($manifest->hasCapability('custom_hook'));
        $this->assertSame('custom_hook', ModuleCapability::labelFor('custom_hook'));
    }

    public function test_manager_discovers_extension_module_from_disk(): void
    {
        $this->writeModule('discord_notify', [
            'name' => 'discord_notify',
            'version' => '1.0.0',
            'capabilities' => [
                ModuleCapability::Extension->value,
                ModuleCapability::NotificationChannel->value,
            ],
            'hooks' => [
                'events' => ['invoice.paid'],
            ],
        ]);

        $manifest = app(ModuleManager::class)->discover()->firstWhere('key', 'discord_notify');

        $this->assertNotNull($manifest);
        $this->assertSame(ModuleProfile::Extension, $manifest->profile());
        $this->assertSame(['invoice.paid'], $manifest->hookManifest->events);
    }

    public function test_admin_module_detail_shows_profile_and_declared_hooks(): void
    {
        $this->withoutVite();
        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.extension-modules',
            'corepanel.themes.auto_load_active' => false,
        ]);

        $this->writeModule('discord_notify', [
            'name' => 'discord_notify',
            'label' => 'Discord Notify',
            'version' => '1.0.0',
            'description' => 'Send Discord alerts on billing events',
            'capabilities' => [
                ModuleCapability::Extension->value,
                ModuleCapability::NotificationChannel->value,
            ],
            'hooks' => [
                'events' => ['invoice.paid'],
            ],
        ]);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.modules.show', 'discord_notify'))
            ->assertOk()
            ->assertSee('Discord Notify')
            ->assertSee(__('Extension module'))
            ->assertSee(__('Notification channel'))
            ->assertSee('invoice.paid')
            ->assertSee(__('Declared hooks'));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function manifestFromFile(array $manifest): ModuleManifest
    {
        $directory = $this->modulesPath.'/manifest-'.uniqid('', true);
        File::ensureDirectoryExists($directory);
        File::put(
            $directory.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return ModuleManifest::fromDirectory($directory);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeModule(string $directory, array $manifest): void
    {
        $path = $this->modulesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }
}
