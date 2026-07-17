<?php

namespace Tests\Feature\Products;

use Core\Products\DataTransferObjects\ProductCategoryData;
use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Services\ProductCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductCategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductCategoryService $categoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryService = app(ProductCategoryService::class);
    }

    public function test_category_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ProductCategoryService::class),
            app(ProductCategoryService::class),
        );
    }

    public function test_create_and_update_category_with_parent(): void
    {
        $parent = $this->categoryService->create(ProductCategoryData::fromArray([
            'name' => 'Root',
            'slug' => 'root',
            'status' => ProductCategoryStatus::Active->value,
        ]));

        $child = $this->categoryService->create(ProductCategoryData::fromArray([
            'name' => 'Child',
            'slug' => 'child',
            'parent_id' => $parent->id,
            'status' => ProductCategoryStatus::Active->value,
            'sort_order' => 5,
        ]));

        $this->assertSame($parent->id, $child->parent_id);

        $updated = $this->categoryService->update($child, ProductCategoryData::fromArray([
            'name' => 'Child Updated',
            'slug' => 'child-updated',
            'parent_id' => null,
            'status' => ProductCategoryStatus::Hidden->value,
            'sort_order' => 1,
        ]));

        $this->assertSame('Child Updated', $updated->name);
        $this->assertNull($updated->parent_id);
        $this->assertTrue($updated->status === ProductCategoryStatus::Hidden);
    }

    public function test_rejects_duplicate_slug(): void
    {
        ProductCategory::factory()->create(['slug' => 'taken']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A category with this slug already exists.');

        $this->categoryService->create(ProductCategoryData::fromArray([
            'name' => 'Dup',
            'slug' => 'taken',
        ]));
    }

    public function test_rejects_parent_cycle_on_update(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'cycle-root']);
        $child = ProductCategory::factory()->create([
            'slug' => 'cycle-leaf',
            'parent_id' => $parent->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set a descendant category as parent');

        $this->categoryService->update($parent, ProductCategoryData::fromArray([
            'name' => $parent->name,
            'slug' => $parent->slug,
            'parent_id' => $child->id,
            'status' => $parent->status->value,
        ]));
    }

    public function test_cannot_delete_category_with_products_or_children(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'with-child']);
        ProductCategory::factory()->create([
            'slug' => 'nested-child',
            'parent_id' => $parent->id,
        ]);

        try {
            $this->categoryService->delete($parent);
            $this->fail('Expected exception for child categories.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('child categories', $exception->getMessage());
        }

        $busy = ProductCategory::factory()->create(['slug' => 'with-products']);
        Product::factory()->create([
            'category_id' => $busy->id,
            'slug' => 'linked-product',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('still has products');

        $this->categoryService->delete($busy);
    }
}
