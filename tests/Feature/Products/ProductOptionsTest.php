<?php

namespace Tests\Feature\Products;

use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\ProductOptionType;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductOption;
use Core\Products\Services\ProductService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductOptionsTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = app(ProductService::class);
    }

    public function test_create_persists_configurable_options(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Configurable VPS',
            'slug' => 'configurable-vps',
            'options' => [
                [
                    'key' => 'hostname',
                    'name' => 'Hostname',
                    'type' => ProductOptionType::Text->value,
                    'required' => true,
                    'sort_order' => 1,
                    'config' => ['max_length' => 64],
                ],
                [
                    'name' => 'RAM Size',
                    'type' => ProductOptionType::Select->value,
                    'required' => true,
                    'sort_order' => 2,
                    'config' => [
                        'choices' => [
                            ['value' => '2gb', 'label' => '2 GB', 'price_delta' => 0],
                            ['value' => '4gb', 'label' => '4 GB', 'price_delta' => 5],
                        ],
                    ],
                ],
                [
                    'key' => 'extra_ips',
                    'name' => 'Extra IPs',
                    'type' => ProductOptionType::Quantity->value,
                    'sort_order' => 3,
                    'config' => ['min' => 0, 'max' => 5, 'step' => 1],
                ],
            ],
        ]));

        $this->assertTrue($product->relationLoaded('options'));
        $this->assertCount(3, $product->options);
        $this->assertSame(['hostname', 'ram_size', 'extra_ips'], $product->options->pluck('key')->all());

        $ram = $product->optionByKey('ram_size');
        $this->assertNotNull($ram);
        $this->assertTrue($ram->type === ProductOptionType::Select);
        $this->assertTrue($ram->required);
        $this->assertCount(2, $ram->choices());
        $this->assertCount(2, $product->requiredOptions);
    }

    public function test_update_syncs_options_by_key(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Options Sync',
            'slug' => 'options-sync',
            'options' => [
                [
                    'key' => 'location',
                    'name' => 'Location',
                    'type' => ProductOptionType::Select->value,
                    'config' => [
                        'choices' => [
                            ['value' => 'eu', 'label' => 'EU'],
                        ],
                    ],
                ],
                [
                    'key' => 'notes',
                    'name' => 'Notes',
                    'type' => ProductOptionType::Text->value,
                ],
            ],
        ]));

        $updated = $this->productService->update($product, ProductData::fromArray([
            'name' => 'Options Sync',
            'slug' => 'options-sync',
            'status' => ProductStatus::Draft->value,
            'options' => [
                [
                    'key' => 'location',
                    'name' => 'Datacenter',
                    'type' => ProductOptionType::Select->value,
                    'required' => true,
                    'config' => [
                        'choices' => [
                            ['value' => 'eu', 'label' => 'EU'],
                            ['value' => 'us', 'label' => 'US'],
                        ],
                    ],
                ],
                [
                    'key' => 'backup',
                    'name' => 'Backup',
                    'type' => ProductOptionType::Checkbox->value,
                    'config' => ['price_delta' => 3],
                ],
            ],
        ]));

        $this->assertCount(2, $updated->options);
        $this->assertSame('Datacenter', $updated->optionByKey('location')?->name);
        $this->assertCount(2, $updated->optionByKey('location')?->choices() ?? []);
        $this->assertNotNull($updated->optionByKey('backup'));
        $this->assertNull($updated->optionByKey('notes'));
        $this->assertDatabaseMissing('product_options', [
            'product_id' => $product->id,
            'key' => 'notes',
        ]);
    }

    public function test_select_option_requires_choices(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Select options require a non-empty choices config.');

        ProductData::fromArray([
            'name' => 'Bad Select',
            'slug' => 'bad-select',
            'options' => [
                [
                    'name' => 'Plan',
                    'type' => ProductOptionType::Select->value,
                    'config' => ['choices' => []],
                ],
            ],
        ]);
    }

    public function test_rejects_duplicate_option_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate option key [slot].');

        ProductData::fromArray([
            'name' => 'Dup Keys',
            'slug' => 'dup-keys',
            'options' => [
                ['key' => 'slot', 'name' => 'Slot A', 'type' => 'text'],
                ['key' => 'slot', 'name' => 'Slot B', 'type' => 'text'],
            ],
        ]);
    }

    public function test_rejects_invalid_option_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid product option type [wizard].');

        ProductData::fromArray([
            'name' => 'Bad Type',
            'slug' => 'bad-option-type',
            'options' => [
                ['name' => 'Weird', 'type' => 'wizard'],
            ],
        ]);
    }

    public function test_unique_option_key_per_product(): void
    {
        $product = Product::factory()->create(['slug' => 'unique-option-key']);

        ProductOption::factory()->create([
            'product_id' => $product->id,
            'key' => 'cpu',
        ]);

        $this->expectException(QueryException::class);

        ProductOption::factory()->create([
            'product_id' => $product->id,
            'key' => 'cpu',
        ]);
    }

    public function test_deleting_product_cascades_options(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Cascade Options',
            'slug' => 'cascade-options',
            'options' => [
                ['name' => 'Label', 'type' => ProductOptionType::Text->value],
            ],
        ]));

        $optionId = $product->options->firstOrFail()->id;

        // Soft delete keeps FK rows; hard delete verifies cascade.
        $product->forceDelete();

        $this->assertDatabaseMissing('product_options', ['id' => $optionId]);
    }
}
