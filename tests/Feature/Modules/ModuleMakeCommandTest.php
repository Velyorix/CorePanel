<?php

namespace Tests\Feature\Modules;

use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Enums\ModuleProfile;
use Core\Modules\Enums\ModuleScaffoldProfile;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModuleScaffolder;
use Core\Modules\Support\ModuleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleMakeCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/modules-make-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
        ]);

        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(ModuleScaffolder::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_module_make_scaffolds_integration_package(): void
    {
        $exitCode = Artisan::call('module:make', [
            'name' => 'Acme Bridge',
            '--author' => 'Velyorix',
            '--description' => 'Acme integration module',
        ]);

        $this->assertSame(0, $exitCode);

        $root = $this->modulesPath.'/AcmeBridge';

        $this->assertDirectoryExists($root);
        $this->assertFileExists($root.'/module.json');
        $this->assertFileExists($root.'/AcmeBridgeModule.php');
        $this->assertFileExists($root.'/Providers/AcmeBridgeServiceProvider.php');
        $this->assertFileExists($root.'/routes/web.php');

        $manifest = json_decode(File::get($root.'/module.json'), true);

        $this->assertSame('acme_bridge', $manifest['name']);
        $this->assertSame('Acme Bridge', $manifest['label']);
        $this->assertSame(['other'], $manifest['capabilities']);
        $this->assertSame('Acme integration module', $manifest['description']);

        $this->assertTrue(app(ModuleManager::class)->has('acme_bridge'));
    }

    public function test_module_make_supports_extension_profile(): void
    {
        Artisan::call('module:make', [
            'name' => 'Discord Notify',
            '--profile' => ModuleScaffoldProfile::Extension->value,
            '--key' => 'discord_notify',
        ]);

        $root = $this->modulesPath.'/DiscordNotify';
        $manifest = json_decode(File::get($root.'/module.json'), true);

        $this->assertSame('discord_notify', $manifest['name']);
        $this->assertSame(['extension', 'notification_channel'], $manifest['capabilities']);
        $this->assertSame(['invoice.paid'], $manifest['hooks']['events']);
        $this->assertFileExists($root.'/Notifications/DiscordNotifyNotificationChannel.php');

        $moduleClass = File::get($root.'/DiscordNotifyModule.php');
        $this->assertStringContainsString(
            'use Modules\\DiscordNotify\\Notifications\\DiscordNotifyNotificationChannel;',
            $moduleClass,
        );
        $this->assertStringContainsString(
            'private function channel(): DiscordNotifyNotificationChannel',
            $moduleClass,
        );

        $descriptor = app(ModuleManager::class)->get('discord_notify');

        $this->assertNotNull($descriptor);
        $this->assertSame(ModuleProfile::Extension, $descriptor->profile());
    }

    public function test_module_make_supports_payment_gateway_profile(): void
    {
        Artisan::call('module:make', [
            'name' => 'Stripe Billing',
            '--profile' => ModuleScaffoldProfile::PaymentGateway->value,
            '--key' => 'stripe_billing',
        ]);

        $root = $this->modulesPath.'/StripeBilling';
        $manifest = json_decode(File::get($root.'/module.json'), true);

        $this->assertSame(['payment_gateway'], $manifest['capabilities']);
        $this->assertSame(
            ['Modules\\StripeBilling\\Gateways\\StripeBillingPaymentGateway'],
            $manifest['gateways'],
        );
        $this->assertFileExists($root.'/Gateways/StripeBillingPaymentGateway.php');
    }

    public function test_module_make_fails_when_directory_already_exists(): void
    {
        File::ensureDirectoryExists($this->modulesPath.'/Demo');

        $exitCode = Artisan::call('module:make', ['name' => 'Demo']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', Artisan::output());
    }

    public function test_module_name_parser_normalizes_directory_and_key(): void
    {
        $parsed = ModuleName::fromInput('dark-pro');

        $this->assertSame('DarkPro', $parsed->directory);
        $this->assertSame('dark_pro', $parsed->key);
        $this->assertSame('Dark Pro', $parsed->label);
        $this->assertSame('Modules\\DarkPro', $parsed->namespace);
    }

    public function test_module_make_merges_extra_capabilities(): void
    {
        Artisan::call('module:make', [
            'name' => 'Hybrid',
            '--profile' => ModuleScaffoldProfile::Integration->value,
            '--capability' => [ModuleCapability::ServerProvider->value],
        ]);

        $manifest = json_decode(File::get($this->modulesPath.'/Hybrid/module.json'), true);

        $this->assertContains('other', $manifest['capabilities']);
        $this->assertContains('server_provider', $manifest['capabilities']);
    }
}
