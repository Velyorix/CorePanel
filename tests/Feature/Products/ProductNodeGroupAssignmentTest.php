<?php

namespace Tests\Feature\Products;

use Core\Nodes\DataTransferObjects\NodeGroupData;
use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Services\NodeGroupService;
use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductNodeGroupAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $productService;

    private NodeGroupService $nodeGroupService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = app(ProductService::class);
        $this->nodeGroupService = app(NodeGroupService::class);
    }

    public function test_node_group_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeGroupService::class),
            app(NodeGroupService::class),
        );
    }

    public function test_can_create_node_group_and_assign_by_key(): void
    {
        $group = $this->nodeGroupService->create(NodeGroupData::fromArray([
            'name' => 'EU West',
            'key' => 'eu-west',
            'location' => 'Amsterdam',
            'type' => NodeGroupType::Vps->value,
        ]));

        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'VPS Starter',
            'slug' => 'vps-starter',
            'type' => ProductType::Vps->value,
            'module' => 'proxmox',
            'provisioning_rules' => [
                'auto_provision' => true,
                'node_group_key' => 'eu-west',
            ],
        ]));

        $this->assertSame($group->id, $product->provisioningRules->node_group_id);
        $this->assertSame('eu-west', $product->nodeGroupKey());
        $this->assertTrue($product->assignedNodeGroup()?->is($group));
        $this->assertTrue($group->type === NodeGroupType::Vps);
        $this->assertTrue($group->status === NodeGroupStatus::Active);
    }

    public function test_can_assign_node_group_by_id(): void
    {
        $group = NodeGroup::factory()->ofType(NodeGroupType::Game)->create([
            'key' => 'game-eu',
            'name' => 'Game EU',
        ]);

        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Minecraft',
            'slug' => 'minecraft',
            'type' => ProductType::Game->value,
            'module' => 'pterodactyl',
            'provisioning_rules' => [
                'node_group_id' => $group->id,
            ],
        ]));

        $this->assertSame($group->id, $product->provisioningRules->node_group_id);
        $this->assertSame('game-eu', $product->nodeGroupKey());
    }

    public function test_rejects_unknown_node_group_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The node group [missing-group] does not exist.');

        $this->productService->create(ProductData::fromArray([
            'name' => 'Orphan Group',
            'slug' => 'orphan-group',
            'module' => 'proxmox',
            'provisioning_rules' => [
                'node_group_key' => 'missing-group',
            ],
        ]));
    }

    public function test_rejects_unknown_node_group_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected node group does not exist.');

        $this->productService->create(ProductData::fromArray([
            'name' => 'Bad Id',
            'slug' => 'bad-id',
            'provisioning_rules' => [
                'node_group_id' => 999999,
            ],
        ]));
    }

    public function test_rejects_mismatched_node_group_id_and_key(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'eu-west']);
        NodeGroup::factory()->create(['key' => 'us-east']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The node group id and key do not match.');

        $this->productService->create(ProductData::fromArray([
            'name' => 'Mismatch',
            'slug' => 'mismatch',
            'provisioning_rules' => [
                'node_group_id' => $group->id,
                'node_group_key' => 'us-east',
            ],
        ]));
    }

    public function test_can_clear_node_group_assignment(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'eu-west']);

        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Clear Group',
            'slug' => 'clear-group',
            'module' => 'proxmox',
            'provisioning_rules' => [
                'node_group_id' => $group->id,
            ],
        ]));

        $updated = $this->productService->update($product, ProductData::fromArray([
            'name' => 'Clear Group',
            'slug' => 'clear-group',
            'module' => 'proxmox',
            'status' => ProductStatus::Draft->value,
            'provisioning_rules' => [
                'auto_provision' => false,
                'node_group_id' => null,
                'node_group_key' => null,
            ],
        ]));

        $this->assertNull($updated->provisioningRules->node_group_id);
        $this->assertNull($updated->nodeGroupKey());
        $this->assertNull($updated->assignedNodeGroup());
    }

    public function test_assigned_to_node_group_scope(): void
    {
        $eu = NodeGroup::factory()->create(['key' => 'eu-west']);
        $us = NodeGroup::factory()->create(['key' => 'us-east']);

        Product::factory()
            ->withProvisioningRules([
                'node_group_id' => $eu->id,
                'node_group_key' => $eu->key,
            ])
            ->create(['slug' => 'eu-product']);

        Product::factory()
            ->withProvisioningRules([
                'node_group_id' => $us->id,
                'node_group_key' => $us->key,
            ])
            ->create(['slug' => 'us-product']);

        $this->assertSame(1, Product::query()->assignedToNodeGroup($eu)->count());
        $this->assertSame(1, Product::query()->assignedToNodeGroup($eu->id)->count());
        $this->assertSame(1, Product::query()->assignedToNodeGroup('eu-west')->count());
        $this->assertSame('eu-product', Product::query()->assignedToNodeGroup($eu)->firstOrFail()->slug);
    }

    public function test_force_deleting_node_group_nulls_product_assignment(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'eu-west']);

        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Linked',
            'slug' => 'linked',
            'provisioning_rules' => [
                'node_group_id' => $group->id,
            ],
        ]));

        $group->forceDelete();

        $product->refresh()->load('provisioningRules');

        $this->assertNull($product->provisioningRules->node_group_id);
        $this->assertSame('eu-west', $product->provisioningRules->node_group_key);
    }

    public function test_node_group_rejects_duplicate_keys(): void
    {
        $this->nodeGroupService->create(NodeGroupData::fromArray([
            'name' => 'EU West',
            'key' => 'eu-west',
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A node group with this key already exists.');

        $this->nodeGroupService->create(NodeGroupData::fromArray([
            'name' => 'EU West Copy',
            'key' => 'eu-west',
        ]));
    }
}
