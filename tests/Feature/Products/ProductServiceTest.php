<?php

namespace Tests\Feature\Products;

use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = app(ProductService::class);
    }

    public function test_product_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ProductService::class),
            app(ProductService::class),
        );
    }

    public function test_create_persists_product_and_pricing_tiers(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'hosting']);

        $product = $this->productService->create(ProductData::fromArray([
            'category_id' => $category->id,
            'name' => 'Starter Hosting',
            'slug' => 'starter-hosting',
            'description' => 'Entry plan',
            'type' => 'other',
            'sort_order' => 10,
            'pricing' => [
                [
                    'billing_cycle' => BillingCycle::Monthly->value,
                    'price' => '9.99',
                    'setup_fee' => '2.00',
                ],
                [
                    'billing_cycle' => BillingCycle::Annual->value,
                    'price' => '99.00',
                    'setup_fee' => 0,
                ],
            ],
        ]));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $category->id,
            'name' => 'Starter Hosting',
            'slug' => 'starter-hosting',
            'status' => ProductStatus::Draft->value,
            'sort_order' => 10,
        ]);

        $this->assertTrue($product->status === ProductStatus::Draft);
        $this->assertTrue($product->relationLoaded('category'));
        $this->assertTrue($product->relationLoaded('pricing'));
        $this->assertCount(2, $product->pricing);
        $this->assertNotNull($product->pricingFor(BillingCycle::Monthly));
        $this->assertSame('9.99', $product->pricingFor(BillingCycle::Monthly)?->price);
        $this->assertSame('2.00', $product->pricingFor(BillingCycle::Monthly)?->setup_fee);
    }

    public function test_create_generates_slug_from_name_when_missing(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Cloud VPS Pro',
        ]));

        $this->assertSame('cloud-vps-pro', $product->slug);
    }

    public function test_create_rejects_unknown_category(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected product category does not exist.');

        $this->productService->create(ProductData::fromArray([
            'name' => 'Broken',
            'slug' => 'broken',
            'category_id' => 999999,
        ]));
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        Product::factory()->create(['slug' => 'taken-slug']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A product with this slug already exists.');

        $this->productService->create(ProductData::fromArray([
            'name' => 'Other',
            'slug' => 'taken-slug',
        ]));
    }

    public function test_create_rejects_duplicate_pricing_cycles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate billing cycle [monthly].');

        $this->productService->create(ProductData::fromArray([
            'name' => 'Dup Cycle',
            'slug' => 'dup-cycle',
            'pricing' => [
                ['billing_cycle' => 'monthly', 'price' => '10'],
                ['billing_cycle' => 'monthly', 'price' => '12'],
            ],
        ]));
    }

    public function test_update_syncs_attributes_and_pricing(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'vps']);
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'pricing' => [
                ['billing_cycle' => 'monthly', 'price' => '10.00'],
                ['billing_cycle' => 'annual', 'price' => '100.00'],
            ],
        ]));

        $updated = $this->productService->update($product, ProductData::fromArray([
            'category_id' => $category->id,
            'name' => 'New Name',
            'slug' => 'new-name',
            'status' => ProductStatus::Draft->value,
            'pricing' => [
                ['billing_cycle' => 'monthly', 'price' => '15.00', 'setup_fee' => '1.00'],
                ['billing_cycle' => 'quarterly', 'price' => '40.00'],
            ],
        ]));

        $this->assertSame('New Name', $updated->name);
        $this->assertSame('new-name', $updated->slug);
        $this->assertSame($category->id, $updated->category_id);
        $this->assertCount(1, $updated->pricing->where('billing_cycle', BillingCycle::Monthly));
        $this->assertSame('15.00', $updated->pricingFor(BillingCycle::Monthly)?->price);
        $this->assertNotNull($updated->pricingFor(BillingCycle::Quarterly));
        $this->assertNull($updated->pricingFor(BillingCycle::Annual));
    }

    public function test_update_rejects_status_change(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::Draft,
            'slug' => 'status-locked',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Product status cannot be changed via update.');

        $this->productService->update($product, ProductData::fromArray([
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => ProductStatus::Published->value,
        ]));
    }

    public function test_publish_unpublish_archive_and_restore_lifecycle(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Lifecycle Product',
            'slug' => 'lifecycle-product',
        ]));

        $published = $this->productService->publish($product);
        $this->assertTrue($published->status === ProductStatus::Published);

        $unpublished = $this->productService->unpublish($published);
        $this->assertTrue($unpublished->status === ProductStatus::Draft);

        $archived = $this->productService->archive($unpublished);
        $this->assertTrue($archived->status === ProductStatus::Archived);

        $restored = $this->productService->restore($archived);
        $this->assertTrue($restored->status === ProductStatus::Draft);
    }

    public function test_publish_is_idempotent_when_already_published(): void
    {
        $product = Product::factory()->published()->create(['slug' => 'already-pub']);

        $result = $this->productService->publish($product);

        $this->assertTrue($result->status === ProductStatus::Published);
    }

    public function test_cannot_publish_archived_product(): void
    {
        $product = Product::factory()->archived()->create(['slug' => 'archived-block']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only draft products can be published.');

        $this->productService->publish($product);
    }

    public function test_cannot_unpublish_archived_product(): void
    {
        $product = Product::factory()->archived()->create(['slug' => 'archived-unpub']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only published products can be unpublished.');

        $this->productService->unpublish($product);
    }

    public function test_delete_soft_deletes_product(): void
    {
        $product = Product::factory()->create(['slug' => 'to-delete']);

        $this->productService->delete($product);

        $this->assertSoftDeleted($product);
    }
}
