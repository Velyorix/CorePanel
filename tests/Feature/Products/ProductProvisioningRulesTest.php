<?php

namespace Tests\Feature\Products;

use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\ProductModuleCapability;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductProvisioningRulesTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = app(ProductService::class);
    }

    public function test_create_persists_provisioning_rules(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Auto VPS',
            'slug' => 'auto-vps',
            'type' => ProductType::Vps->value,
            'module' => 'proxmox',
            'module_capabilities' => [ProductModuleCapability::ServerCreate->value],
            'provisioning_rules' => [
                'auto_provision' => true,
                'send_welcome_email' => true,
                'welcome_email_template' => 'products.welcome.vps',
                'node_group_key' => 'eu-west',
                'config' => ['priority' => 'high'],
            ],
        ]));

        $this->assertTrue($product->relationLoaded('provisioningRules'));
        $this->assertNotNull($product->provisioningRules);
        $this->assertTrue($product->shouldAutoProvision());
        $this->assertTrue($product->shouldSendWelcomeEmail());
        $this->assertSame('eu-west', $product->nodeGroupKey());
        $this->assertSame('products.welcome.vps', $product->provisioningRules->welcome_email_template);
        $this->assertSame(['priority' => 'high'], $product->provisioningRules->config);
        $this->assertTrue($product->provisioningRules->hasNodeGroup());
    }

    public function test_create_without_rules_uses_defaults(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Manual Product',
            'slug' => 'manual-product',
        ]));

        $this->assertNotNull($product->provisioningRules);
        $this->assertFalse($product->shouldAutoProvision());
        $this->assertTrue($product->shouldSendWelcomeEmail());
        $this->assertNull($product->nodeGroupKey());
    }

    public function test_update_changes_provisioning_rules(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Rules Update',
            'slug' => 'rules-update',
            'module' => 'pterodactyl',
            'provisioning_rules' => [
                'auto_provision' => false,
                'send_welcome_email' => true,
                'node_group_key' => 'eu-central',
            ],
        ]));

        $updated = $this->productService->update($product, ProductData::fromArray([
            'name' => 'Rules Update',
            'slug' => 'rules-update',
            'module' => 'pterodactyl',
            'status' => ProductStatus::Draft->value,
            'provisioning_rules' => [
                'auto_provision' => true,
                'send_welcome_email' => false,
                'welcome_email_template' => null,
                'node_group_key' => 'us-east',
            ],
        ]));

        $this->assertTrue($updated->shouldAutoProvision());
        $this->assertFalse($updated->shouldSendWelcomeEmail());
        $this->assertSame('us-east', $updated->nodeGroupKey());
        $this->assertDatabaseCount('product_provisioning_rules', 1);
    }

    public function test_omitting_rules_on_update_keeps_existing(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Keep Rules',
            'slug' => 'keep-rules',
            'module' => 'proxmox',
            'provisioning_rules' => [
                'auto_provision' => true,
                'node_group_key' => 'eu-west',
            ],
        ]));

        $updated = $this->productService->update($product, ProductData::fromArray([
            'name' => 'Keep Rules Renamed',
            'slug' => 'keep-rules',
            'module' => 'proxmox',
            'status' => ProductStatus::Draft->value,
        ]));

        $this->assertSame('Keep Rules Renamed', $updated->name);
        $this->assertTrue($updated->shouldAutoProvision());
        $this->assertSame('eu-west', $updated->nodeGroupKey());
    }

    public function test_auto_provision_requires_module(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Auto-provisioning requires a provider module to be set.');

        ProductData::fromArray([
            'name' => 'No Module Auto',
            'slug' => 'no-module-auto',
            'provisioning_rules' => [
                'auto_provision' => true,
            ],
        ]);
    }

    public function test_rejects_invalid_node_group_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid node group key');

        ProductData::fromArray([
            'name' => 'Bad Group',
            'slug' => 'bad-group',
            'provisioning_rules' => [
                'node_group_key' => 'EU West!',
            ],
        ]);
    }

    public function test_auto_provisionable_scope(): void
    {
        Product::factory()
            ->withModule('proxmox')
            ->withProvisioningRules(['auto_provision' => true, 'node_group_key' => 'eu-west'])
            ->create(['slug' => 'auto-on']);

        Product::factory()
            ->withProvisioningRules(['auto_provision' => false])
            ->create(['slug' => 'auto-off']);

        $this->assertSame(1, Product::query()->autoProvisionable()->count());
        $this->assertSame('auto-on', Product::query()->autoProvisionable()->firstOrFail()->slug);
    }

    public function test_deleting_product_cascades_provisioning_rules(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Cascade Rules',
            'slug' => 'cascade-rules',
            'provisioning_rules' => [
                'send_welcome_email' => false,
            ],
        ]));

        $rulesId = $product->provisioningRules->id;

        $product->forceDelete();

        $this->assertDatabaseMissing('product_provisioning_rules', ['id' => $rulesId]);
    }
}
