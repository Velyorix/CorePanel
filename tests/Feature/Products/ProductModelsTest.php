<?php

namespace Tests\Feature\Products;

use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Models\ProductPricing;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_parent_children_and_products_relations(): void
    {
        $parent = ProductCategory::factory()->create([
            'name' => 'Hosting',
            'slug' => 'hosting',
            'sort_order' => 1,
        ]);

        $child = ProductCategory::factory()->childOf($parent)->create([
            'name' => 'Shared',
            'slug' => 'shared',
            'sort_order' => 2,
        ]);

        $product = Product::factory()->create([
            'category_id' => $child->id,
            'name' => 'Starter Hosting',
            'slug' => 'starter-hosting',
        ]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains($child));
        $this->assertTrue($child->products->contains($product));
        $this->assertTrue($product->category->is($child));
        $this->assertTrue($parent->status === ProductCategoryStatus::Active);
    }

    public function test_product_pricing_tiers_and_billing_cycle_casts(): void
    {
        $product = Product::factory()
            ->published()
            ->withPricing([BillingCycle::Monthly, BillingCycle::Annual], '12.50', '5.00')
            ->create([
                'name' => 'VPS Medium',
                'slug' => 'vps-medium',
            ]);

        $this->assertTrue($product->status === ProductStatus::Published);
        $this->assertCount(2, $product->pricing);

        $monthly = $product->pricingFor(BillingCycle::Monthly);
        $this->assertNotNull($monthly);
        $this->assertTrue($monthly->billing_cycle === BillingCycle::Monthly);
        $this->assertSame('12.50', $monthly->price);
        $this->assertSame('5.00', $monthly->setup_fee);
        $this->assertTrue($monthly->is_enabled);
        $this->assertTrue($monthly->product->is($product));

        $annual = $product->pricingFor(BillingCycle::Annual);
        $this->assertNotNull($annual);
        $this->assertTrue($annual->billing_cycle === BillingCycle::Annual);
    }

    public function test_enabled_pricing_excludes_disabled_tiers(): void
    {
        $product = Product::factory()->create(['slug' => 'pricing-filter']);

        ProductPricing::factory()->create([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly,
            'is_enabled' => true,
        ]);

        ProductPricing::factory()->disabled()->create([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Annual,
        ]);

        $this->assertCount(2, $product->fresh()->pricing);
        $this->assertCount(1, $product->enabledPricing);
        $this->assertTrue($product->enabledPricing->first()->billing_cycle === BillingCycle::Monthly);
    }

    public function test_product_and_category_support_soft_delete(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'soft-cat']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'soft-product',
        ]);

        $product->delete();
        $category->delete();

        $this->assertSoftDeleted($product);
        $this->assertSoftDeleted($category);
    }

    public function test_unique_pricing_cycle_per_product(): void
    {
        $product = Product::factory()->create(['slug' => 'unique-cycle']);

        ProductPricing::factory()->create([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly,
        ]);

        $this->expectException(QueryException::class);

        ProductPricing::factory()->create([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly,
        ]);
    }

    public function test_billing_cycle_enum_exposes_all_tome_cycles(): void
    {
        $this->assertSame([
            'hourly',
            'daily',
            'weekly',
            'monthly',
            'quarterly',
            'semi_annual',
            'annual',
            'custom',
        ], BillingCycle::values());
    }
}
