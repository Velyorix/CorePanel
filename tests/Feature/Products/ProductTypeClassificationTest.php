<?php

namespace Tests\Feature\Products;

use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductTypeClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_type_enum_exposes_roadmap_types(): void
    {
        $this->assertSame([
            'hosting',
            'vps',
            'game',
            'domain',
            'addon',
            'other',
        ], ProductType::values());
    }

    public function test_create_persists_typed_product(): void
    {
        $product = app(ProductService::class)->create(ProductData::fromArray([
            'name' => 'Minecraft Starter',
            'slug' => 'minecraft-starter',
            'type' => ProductType::Game->value,
        ]));

        $this->assertTrue($product->type === ProductType::Game);
        $this->assertTrue($product->type->requiresHostname());
        $this->assertFalse($product->type->isAddon());

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'type' => ProductType::Game->value,
        ]);
    }

    public function test_create_rejects_invalid_product_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid product type [banana].');

        ProductData::fromArray([
            'name' => 'Bad Type',
            'slug' => 'bad-type',
            'type' => 'banana',
        ]);
    }

    public function test_defaults_to_other_when_type_omitted(): void
    {
        $product = app(ProductService::class)->create(ProductData::fromArray([
            'name' => 'Generic Product',
            'slug' => 'generic-product',
        ]));

        $this->assertTrue($product->type === ProductType::Other);
    }

    public function test_of_type_and_catalog_scopes(): void
    {
        Product::factory()->ofType(ProductType::Hosting)->create(['slug' => 'host-1']);
        Product::factory()->ofType(ProductType::Vps)->create(['slug' => 'vps-1']);
        Product::factory()->ofType(ProductType::Addon)->create(['slug' => 'addon-1']);

        $this->assertSame(1, Product::query()->ofType(ProductType::Hosting)->count());
        $this->assertSame(2, Product::query()->catalog()->count());
        $this->assertFalse(
            Product::query()->catalog()->get()->contains(
                fn (Product $product): bool => $product->type === ProductType::Addon,
            ),
        );
    }

    public function test_domain_and_addon_classification_helpers(): void
    {
        $this->assertTrue(ProductType::Domain->requiresHostname());
        $this->assertSame('domain', ProductType::Domain->hostnameOptionKey());
        $this->assertSame('hostname', ProductType::Vps->hostnameOptionKey());
        $this->assertNull(ProductType::Addon->hostnameOptionKey());
        $this->assertTrue(ProductType::Addon->isAddon());
        $this->assertFalse(ProductType::Addon->requiresHostname());
        $this->assertContains(ProductType::Hosting, ProductType::catalogTypes());
        $this->assertNotContains(ProductType::Addon, ProductType::catalogTypes());
    }
}
