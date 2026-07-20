<?php

namespace Tests\Feature\Services;

use Core\Products\Enums\ProductModuleCapability;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Services\Contracts\ModuleAccessLinkProvider;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceAccessService;
use Core\Services\Services\ServiceConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceAccessService $access;

    private ServiceConfigService $configs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(ServiceAccessService::class);
        $this->configs = app(ServiceConfigService::class);
    }

    public function test_service_access_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ServiceAccessService::class),
            app(ServiceAccessService::class),
        );
    }

    public function test_missing_urls_are_not_openable(): void
    {
        $service = Service::factory()->active()->create();

        $links = $this->access->for($service);

        $this->assertNull($links->panelUrl);
        $this->assertNull($links->consoleUrl);
        $this->assertFalse($links->canOpenPanel);
        $this->assertFalse($links->canOpenConsole);
        $this->assertFalse($links->hasPanel());
        $this->assertFalse($links->hasConsole());
    }

    public function test_access_urls_from_encrypted_config_are_openable_when_active(): void
    {
        $service = Service::factory()->active()->create();

        $this->configs->set($service, [
            'access' => [
                'panel_url' => 'https://panel.example/server/abc',
                'console_url' => 'https://panel.example/server/abc/console',
            ],
        ]);

        $links = $this->access->for($service);

        $this->assertSame('https://panel.example/server/abc', $links->panelUrl);
        $this->assertSame('https://panel.example/server/abc/console', $links->consoleUrl);
        $this->assertTrue($links->canOpenPanel);
        $this->assertTrue($links->canOpenConsole);
        $this->assertSame([
            'panel_url' => 'https://panel.example/server/abc',
            'console_url' => 'https://panel.example/server/abc/console',
            'can_open_panel' => true,
            'can_open_console' => true,
        ], $links->toArray());
    }

    public function test_suspended_service_cannot_open_links_even_with_urls(): void
    {
        $service = Service::factory()->suspended()->create();

        $this->configs->set($service, [
            'access' => [
                'panel_url' => 'https://panel.example/server/abc',
                'console_url' => 'https://panel.example/server/abc/console',
            ],
        ]);

        $links = $this->access->for($service);

        $this->assertTrue($links->hasPanel());
        $this->assertTrue($links->hasConsole());
        $this->assertFalse($links->canOpenPanel);
        $this->assertFalse($links->canOpenConsole);
    }

    public function test_capability_gate_blocks_when_product_lists_capabilities_without_access(): void
    {
        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('pterodactyl', [
                ProductModuleCapability::ServerCreate,
                ProductModuleCapability::ServerSuspend,
            ])
            ->create();

        $service = Service::factory()->active()->create([
            'product_id' => $product->id,
            'module' => 'pterodactyl',
        ]);

        $this->configs->set($service, [
            'access' => [
                'panel_url' => 'https://panel.example/server/abc',
                'console_url' => 'https://panel.example/server/abc/console',
            ],
        ]);

        $links = $this->access->for($service);

        $this->assertFalse($links->canOpenPanel);
        $this->assertFalse($links->canOpenConsole);
    }

    public function test_capability_gate_allows_when_product_declares_panel_and_console(): void
    {
        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('pterodactyl', [
                ProductModuleCapability::ServerPanel,
                ProductModuleCapability::ServerConsole,
            ])
            ->create();

        $service = Service::factory()->active()->create([
            'product_id' => $product->id,
            'module' => 'pterodactyl',
        ]);

        $this->configs->set($service, [
            'credentials' => [
                'panel_url' => 'https://panel.example/from-credentials',
            ],
            'console_url' => 'https://panel.example/from-top-level',
        ]);

        $links = $this->access->for($service);

        $this->assertSame('https://panel.example/from-credentials', $links->panelUrl);
        $this->assertSame('https://panel.example/from-top-level', $links->consoleUrl);
        $this->assertTrue($links->canOpenPanel);
        $this->assertTrue($links->canOpenConsole);
    }

    public function test_module_provider_urls_are_used_when_config_has_none(): void
    {
        $this->app->instance(ModuleAccessLinkProvider::class, new class implements ModuleAccessLinkProvider
        {
            public function panelUrl(Service $service): ?string
            {
                return 'https://module.example/panel/'.$service->id;
            }

            public function consoleUrl(Service $service): ?string
            {
                return 'https://module.example/console/'.$service->id;
            }
        });
        $this->app->forgetInstance(ServiceAccessService::class);

        $service = Service::factory()->active()->create();
        $links = app(ServiceAccessService::class)->for($service);

        $this->assertSame('https://module.example/panel/'.$service->id, $links->panelUrl);
        $this->assertSame('https://module.example/console/'.$service->id, $links->consoleUrl);
        $this->assertTrue($links->canOpenPanel);
        $this->assertTrue($links->canOpenConsole);
    }

    public function test_config_url_takes_priority_over_module_provider(): void
    {
        $this->app->instance(ModuleAccessLinkProvider::class, new class implements ModuleAccessLinkProvider
        {
            public function panelUrl(Service $service): ?string
            {
                return 'https://module.example/panel';
            }

            public function consoleUrl(Service $service): ?string
            {
                return 'https://module.example/console';
            }
        });
        $this->app->forgetInstance(ServiceAccessService::class);

        $service = Service::factory()->active()->create();
        $this->configs->set($service, [
            'access' => [
                'panel_url' => 'https://config.example/panel',
            ],
        ]);

        $links = app(ServiceAccessService::class)->for($service);

        $this->assertSame('https://config.example/panel', $links->panelUrl);
        $this->assertSame('https://module.example/console', $links->consoleUrl);
    }
}
