<?php

namespace Tests\Feature\Products;

use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\ProductModuleCapability;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductModuleLinkTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = app(ProductService::class);
    }

    public function test_create_links_product_to_provider_module_with_capabilities(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Minecraft Node',
            'slug' => 'minecraft-node',
            'type' => ProductType::Game->value,
            'module' => 'Pterodactyl',
            'module_capabilities' => [
                ProductModuleCapability::ServerCreate,
                ProductModuleCapability::ServerSuspend->value,
                'server.unsuspend',
                ProductModuleCapability::ServerTerminate->value,
            ],
        ]));

        $this->assertSame('pterodactyl', $product->module);
        $this->assertTrue($product->hasModule());
        $this->assertTrue($product->usesModule('pterodactyl'));
        $this->assertSame([
            'server.create',
            'server.suspend',
            'server.unsuspend',
            'server.terminate',
        ], $product->requiredCapabilities());
        $this->assertTrue($product->requiresCapability(ProductModuleCapability::ServerCreate));
        $this->assertTrue($product->requiresAllCapabilities('server.create', 'server.terminate'));
        $this->assertFalse($product->requiresCapability(ProductModuleCapability::ServerRestart));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'module' => 'pterodactyl',
        ]);
    }

    public function test_module_without_capabilities_is_allowed(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Proxmox VPS',
            'slug' => 'proxmox-vps',
            'type' => ProductType::Vps->value,
            'module' => 'proxmox',
        ]));

        $this->assertSame('proxmox', $product->module);
        $this->assertSame([], $product->requiredCapabilities());
    }

    public function test_capabilities_without_module_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module capabilities require a provider module to be set.');

        ProductData::fromArray([
            'name' => 'Orphan Caps',
            'slug' => 'orphan-caps',
            'module_capabilities' => [ProductModuleCapability::ServerCreate->value],
        ]);
    }

    public function test_rejects_invalid_module_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The module must be a lowercase slug');

        ProductData::fromArray([
            'name' => 'Bad Module',
            'slug' => 'bad-module',
            'module' => 'Ptero Dactyl!',
        ]);
    }

    public function test_rejects_duplicate_capabilities(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate module capability [server.create].');

        ProductData::fromArray([
            'name' => 'Dup Caps',
            'slug' => 'dup-caps',
            'module' => 'pterodactyl',
            'module_capabilities' => [
                ProductModuleCapability::ServerCreate->value,
                ProductModuleCapability::ServerCreate->value,
            ],
        ]);
    }

    public function test_rejects_invalid_capability_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid module capability [Server Create].');

        ProductData::fromArray([
            'name' => 'Bad Cap',
            'slug' => 'bad-cap',
            'module' => 'pterodactyl',
            'module_capabilities' => ['Server Create'],
        ]);
    }

    public function test_with_module_scope_filters_linked_products(): void
    {
        Product::factory()->withModule('pterodactyl', [
            ProductModuleCapability::ServerCreate,
        ])->create(['slug' => 'mod-a']);

        Product::factory()->withModule('proxmox')->create(['slug' => 'mod-b']);
        Product::factory()->create(['slug' => 'mod-none', 'module' => null]);

        $this->assertSame(2, Product::query()->withModule()->count());
        $this->assertSame(1, Product::query()->withModule('pterodactyl')->count());
        $this->assertTrue(
            Product::query()->withModule('pterodactyl')->firstOrFail()
                ->requiresCapability(ProductModuleCapability::ServerCreate),
        );
    }

    public function test_server_lifecycle_capabilities_are_documented(): void
    {
        $this->assertSame([
            'server.create',
            'server.suspend',
            'server.unsuspend',
            'server.terminate',
        ], array_map(
            static fn (ProductModuleCapability $capability): string => $capability->value,
            ProductModuleCapability::serverLifecycle(),
        ));
    }
}
