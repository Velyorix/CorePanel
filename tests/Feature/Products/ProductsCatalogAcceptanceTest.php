<?php

namespace Tests\Feature\Products;

use App\Models\User;
use Core\Nodes\Models\NodeGroup;
use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Enums\ProductModuleCapability;
use Core\Products\Enums\ProductOptionType;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Services\ProductService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end smoke coverage for the products catalog and configurator.
 */
class ProductsCatalogAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.products-acceptance',
            'session.driver' => 'array',
        ]);
    }

    public function test_admin_product_catalog_happy_path_smoke(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $productService = app(ProductService::class);

        $this->actingAs($admin)
            ->post(route('admin.product-categories.store'), [
                'name' => 'Smoke VPS',
                'slug' => 'smoke-vps',
                'status' => ProductCategoryStatus::Active->value,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $category = ProductCategory::query()->where('slug', 'smoke-vps')->firstOrFail();
        $group = NodeGroup::factory()->create([
            'key' => 'smoke-eu-west',
            'name' => 'Smoke EU West',
        ]);

        $product = $productService->create(ProductData::fromArray([
            'category_id' => $category->id,
            'name' => 'Smoke Cloud VPS',
            'slug' => 'smoke-cloud-vps',
            'type' => ProductType::Vps->value,
            'module' => 'proxmox',
            'module_capabilities' => [
                ProductModuleCapability::ServerCreate->value,
                ProductModuleCapability::ServerSuspend->value,
            ],
            'pricing' => [
                [
                    'billing_cycle' => BillingCycle::Monthly->value,
                    'price' => '24.99',
                    'setup_fee' => '10.00',
                ],
                [
                    'billing_cycle' => BillingCycle::Annual->value,
                    'price' => '249.00',
                    'setup_fee' => '0',
                ],
            ],
            'options' => [
                [
                    'key' => 'hostname',
                    'name' => 'Hostname',
                    'type' => ProductOptionType::Text->value,
                    'required' => true,
                    'config' => ['max_length' => 64],
                ],
                [
                    'name' => 'RAM Size',
                    'type' => ProductOptionType::Select->value,
                    'required' => true,
                    'config' => [
                        'choices' => [
                            ['value' => '4gb', 'label' => '4 GB', 'price_delta' => 0],
                            ['value' => '8gb', 'label' => '8 GB', 'price_delta' => 10],
                        ],
                    ],
                ],
            ],
            'addons' => [
                [
                    'key' => 'backup',
                    'name' => 'Daily Backup',
                    'price' => '3.00',
                    'setup_fee' => '1.00',
                    'billing_cycle' => BillingCycle::Monthly->value,
                ],
            ],
            'provisioning_rules' => [
                'auto_provision' => true,
                'send_welcome_email' => true,
                'welcome_email_template' => 'products.welcome.smoke',
                'node_group_key' => $group->key,
            ],
        ]));

        $this->assertTrue($product->status === ProductStatus::Draft);
        $this->assertCount(2, $product->pricing);
        $this->assertCount(2, $product->options);
        $this->assertCount(1, $product->addons);
        $this->assertTrue($product->shouldAutoProvision());
        $this->assertSame($group->id, $product->provisioningRules?->node_group_id);
        $this->assertTrue($product->requiresCapability(ProductModuleCapability::ServerCreate));

        $this->actingAs($admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Smoke Cloud VPS')
            ->assertSee('24.99')
            ->assertSee('Hostname')
            ->assertSee('Daily Backup')
            ->assertSee('proxmox')
            ->assertSee('Smoke EU West');

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Smoke Cloud VPS Plus',
                'slug' => 'smoke-cloud-vps',
                'category_id' => $category->id,
                'type' => ProductType::Vps->value,
                'module' => 'proxmox',
                'sort_order' => 2,
                'pricing' => [
                    BillingCycle::Monthly->value => [
                        'enabled' => '1',
                        'price' => '29.99',
                        'setup_fee' => '10.00',
                    ],
                    BillingCycle::Annual->value => [
                        'enabled' => '1',
                        'price' => '299.00',
                        'setup_fee' => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.products.show', $product));

        $product->refresh()->load(['options', 'addons', 'provisioningRules', 'pricing']);

        $this->assertSame('Smoke Cloud VPS Plus', $product->name);
        $this->assertSame('29.99', $product->pricingFor(BillingCycle::Monthly)?->price);
        $this->assertCount(2, $product->options);
        $this->assertNotNull($product->optionByKey('hostname'));
        $this->assertNotNull($product->addonByKey('backup'));
        $this->assertTrue($product->shouldAutoProvision());
        $this->assertSame($group->id, $product->provisioningRules?->node_group_id);
        $this->assertTrue($product->status === ProductStatus::Draft);

        $this->actingAs($admin)
            ->post(route('admin.products.publish', $product))
            ->assertRedirect(route('admin.products.show', $product));
        $this->assertTrue($product->fresh()->status === ProductStatus::Published);

        $this->actingAs($admin)
            ->post(route('admin.products.unpublish', $product))
            ->assertRedirect(route('admin.products.show', $product));
        $this->assertTrue($product->fresh()->status === ProductStatus::Draft);

        $this->actingAs($admin)
            ->post(route('admin.products.publish', $product))
            ->assertRedirect(route('admin.products.show', $product));

        $this->actingAs($admin)
            ->post(route('admin.products.archive', $product))
            ->assertRedirect(route('admin.products.show', $product));
        $this->assertTrue($product->fresh()->status === ProductStatus::Archived);

        $this->actingAs($admin)
            ->post(route('admin.products.restore', $product))
            ->assertRedirect(route('admin.products.show', $product));
        $this->assertTrue($product->fresh()->status === ProductStatus::Draft);

        $this->actingAs($admin)
            ->from(route('admin.product-categories.show', $category))
            ->delete(route('admin.product-categories.destroy', $category))
            ->assertRedirect(route('admin.product-categories.show', $category))
            ->assertSessionHasErrors('category');

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('product_options', [
            'product_id' => $product->id,
            'key' => 'hostname',
        ]);
        $this->assertDatabaseHas('product_addons', [
            'product_id' => $product->id,
            'key' => 'backup',
        ]);
    }
}
