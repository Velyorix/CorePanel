<?php

namespace Tests\Feature\Products;

use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Core\Products\Services\ProductService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductAddonsTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = app(ProductService::class);
    }

    public function test_create_persists_addons_with_separate_billing(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Hosting Plus',
            'slug' => 'hosting-plus',
            'pricing' => [
                ['billing_cycle' => BillingCycle::Monthly->value, 'price' => '10.00'],
            ],
            'addons' => [
                [
                    'key' => 'backup',
                    'name' => 'Daily Backup',
                    'description' => 'Nightly snapshots',
                    'price' => '2.50',
                    'setup_fee' => '1.00',
                    'billing_cycle' => BillingCycle::Monthly->value,
                    'sort_order' => 1,
                ],
                [
                    'name' => 'SSL Certificate',
                    'price' => '15.00',
                    'billing_cycle' => BillingCycle::Annual->value,
                    'sort_order' => 2,
                ],
            ],
        ]));

        $this->assertTrue($product->relationLoaded('addons'));
        $this->assertCount(2, $product->addons);
        $this->assertSame(['backup', 'ssl_certificate'], $product->addons->pluck('key')->all());

        $backup = $product->addonByKey('backup');
        $this->assertNotNull($backup);
        $this->assertSame('2.50', $backup->price);
        $this->assertSame('1.00', $backup->setup_fee);
        $this->assertTrue($backup->billing_cycle === BillingCycle::Monthly);
        $this->assertTrue($backup->product->is($product));

        $ssl = $product->addonByKey('ssl_certificate');
        $this->assertNotNull($ssl);
        $this->assertTrue($ssl->billing_cycle === BillingCycle::Annual);
        $this->assertSame('15.00', $ssl->price);

        // Parent pricing cycle is independent from addon cycles.
        $this->assertNotNull($product->pricingFor(BillingCycle::Monthly));
        $this->assertNull($product->pricingFor(BillingCycle::Annual));
    }

    public function test_update_syncs_addons_by_key(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Addon Sync',
            'slug' => 'addon-sync',
            'addons' => [
                [
                    'key' => 'backup',
                    'name' => 'Backup',
                    'price' => '2.00',
                    'billing_cycle' => BillingCycle::Monthly->value,
                ],
                [
                    'key' => 'cdn',
                    'name' => 'CDN',
                    'price' => '5.00',
                    'billing_cycle' => BillingCycle::Monthly->value,
                ],
            ],
        ]));

        $updated = $this->productService->update($product, ProductData::fromArray([
            'name' => 'Addon Sync',
            'slug' => 'addon-sync',
            'status' => ProductStatus::Draft->value,
            'addons' => [
                [
                    'key' => 'backup',
                    'name' => 'Premium Backup',
                    'price' => '4.00',
                    'setup_fee' => '2.00',
                    'billing_cycle' => BillingCycle::Monthly->value,
                ],
                [
                    'key' => 'malware',
                    'name' => 'Malware Scan',
                    'price' => '3.00',
                    'billing_cycle' => BillingCycle::Quarterly->value,
                ],
            ],
        ]));

        $this->assertCount(2, $updated->addons);
        $this->assertSame('Premium Backup', $updated->addonByKey('backup')?->name);
        $this->assertSame('4.00', $updated->addonByKey('backup')?->price);
        $this->assertNotNull($updated->addonByKey('malware'));
        $this->assertNull($updated->addonByKey('cdn'));
        $this->assertDatabaseMissing('product_addons', [
            'product_id' => $product->id,
            'key' => 'cdn',
        ]);
    }

    public function test_enabled_addons_excludes_disabled(): void
    {
        $product = Product::factory()->create(['slug' => 'addon-filter']);

        ProductAddon::factory()->create([
            'product_id' => $product->id,
            'key' => 'on',
            'is_enabled' => true,
        ]);

        ProductAddon::factory()->disabled()->create([
            'product_id' => $product->id,
            'key' => 'off',
        ]);

        $this->assertCount(2, $product->fresh()->addons);
        $this->assertCount(1, $product->enabledAddons);
        $this->assertSame('on', $product->enabledAddons->first()?->key);
    }

    public function test_rejects_invalid_addon_billing_cycle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid addon billing cycle [biweekly].');

        ProductData::fromArray([
            'name' => 'Bad Cycle',
            'slug' => 'bad-addon-cycle',
            'addons' => [
                [
                    'name' => 'Weird',
                    'price' => '1.00',
                    'billing_cycle' => 'biweekly',
                ],
            ],
        ]);
    }

    public function test_rejects_duplicate_addon_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate addon key [backup].');

        ProductData::fromArray([
            'name' => 'Dup Addon',
            'slug' => 'dup-addon',
            'addons' => [
                ['key' => 'backup', 'name' => 'A', 'price' => '1', 'billing_cycle' => 'monthly'],
                ['key' => 'backup', 'name' => 'B', 'price' => '2', 'billing_cycle' => 'monthly'],
            ],
        ]);
    }

    public function test_rejects_negative_addon_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The addon price cannot be negative.');

        ProductData::fromArray([
            'name' => 'Neg Price',
            'slug' => 'neg-addon-price',
            'addons' => [
                [
                    'name' => 'Bad',
                    'price' => '-1.00',
                    'billing_cycle' => BillingCycle::Monthly->value,
                ],
            ],
        ]);
    }

    public function test_unique_addon_key_per_product(): void
    {
        $product = Product::factory()->create(['slug' => 'unique-addon-key']);

        ProductAddon::factory()->create([
            'product_id' => $product->id,
            'key' => 'backup',
        ]);

        $this->expectException(QueryException::class);

        ProductAddon::factory()->create([
            'product_id' => $product->id,
            'key' => 'backup',
        ]);
    }

    public function test_deleting_product_cascades_addons(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Cascade Addons',
            'slug' => 'cascade-addons',
            'addons' => [
                [
                    'name' => 'Extra Disk',
                    'price' => '3.00',
                    'billing_cycle' => BillingCycle::Monthly->value,
                ],
            ],
        ]));

        $addonId = $product->addons->firstOrFail()->id;

        $product->forceDelete();

        $this->assertDatabaseMissing('product_addons', ['id' => $addonId]);
    }
}
