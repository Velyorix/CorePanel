<?php

namespace Tests\Feature\Servers;

use App\Models\User;
use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Models\NodeGroupRelation;
use Core\Products\Models\Product;
use Core\Products\Models\ProductProvisioningRules;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNodeGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-node-groups',
        ]);
    }

    public function test_admin_can_view_groups_index_and_create_form(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $group = NodeGroup::factory()->create([
            'name' => 'EU West Pool',
            'key' => 'eu-west-pool',
            'location' => 'Amsterdam',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.node-groups.index'))
            ->assertOk()
            ->assertSee('EU West Pool')
            ->assertSee('eu-west-pool')
            ->assertSee('Amsterdam');

        $this->actingAs($admin)
            ->get(route('admin.node-groups.create'))
            ->assertOk()
            ->assertSee(__('Create group'))
            ->assertSee(__('Assigned servers'));
    }

    public function test_admin_can_create_group_with_server_assignments(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $nodeA = Node::factory()->forModule('stub')->create(['name' => 'Node A']);
        $nodeB = Node::factory()->forModule('stub')->create(['name' => 'Node B']);

        $this->actingAs($admin)
            ->post(route('admin.node-groups.store'), [
                'name' => 'Game EU',
                'key' => 'game-eu',
                'location' => 'Frankfurt',
                'type' => NodeGroupType::Game->value,
                'description' => 'European game servers',
                'status' => NodeGroupStatus::Active->value,
                'sort_order' => 3,
                'node_ids' => [$nodeA->id, $nodeB->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $group = NodeGroup::query()->where('key', 'game-eu')->firstOrFail();

        $this->assertSame('Game EU', $group->name);
        $this->assertSame(NodeGroupType::Game, $group->type);
        $this->assertSame('Frankfurt', $group->location);
        $this->assertSame(2, $group->assignedNodes()->count());
        $this->assertSame($nodeA->id, $nodeA->fresh()->node_group_id);
        $this->assertSame(2, NodeGroupRelation::query()->where('node_group_id', $group->id)->count());
    }

    public function test_admin_can_update_group_and_sync_assignments(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $group = NodeGroup::factory()->create([
            'name' => 'Old Pool',
            'key' => 'old-pool',
            'type' => NodeGroupType::General,
        ]);
        $keep = Node::factory()->forModule('stub')->create(['name' => 'Keep Node', 'node_group_id' => $group->id]);
        $remove = Node::factory()->forModule('stub')->create(['name' => 'Remove Node', 'node_group_id' => $group->id]);
        $add = Node::factory()->forModule('stub')->create(['name' => 'Add Node']);

        NodeGroupRelation::query()->create([
            'node_group_id' => $group->id,
            'node_id' => $keep->id,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        NodeGroupRelation::query()->create([
            'node_group_id' => $group->id,
            'node_id' => $remove->id,
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.node-groups.update', $group), [
                'name' => 'Updated Pool',
                'key' => 'updated-pool',
                'type' => NodeGroupType::Vps->value,
                'status' => NodeGroupStatus::Disabled->value,
                'sort_order' => 10,
                'node_ids' => [$keep->id, $add->id],
            ])
            ->assertRedirect(route('admin.node-groups.show', $group))
            ->assertSessionHas('status');

        $group->refresh();

        $this->assertSame('Updated Pool', $group->name);
        $this->assertSame('updated-pool', $group->key);
        $this->assertTrue($group->type === NodeGroupType::Vps);
        $this->assertTrue($group->status === NodeGroupStatus::Disabled);
        $this->assertSame(2, $group->assignedNodes()->count());
        $this->assertNull($remove->fresh()->node_group_id);
        $this->assertSame($group->id, $add->fresh()->node_group_id);
    }

    public function test_admin_can_delete_group_without_products(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $group = NodeGroup::factory()->create(['key' => 'disposable']);

        $this->actingAs($admin)
            ->delete(route('admin.node-groups.destroy', $group))
            ->assertRedirect(route('admin.node-groups.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted($group);
    }

    public function test_cannot_delete_group_linked_to_products(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $group = NodeGroup::factory()->create(['key' => 'linked-pool']);
        $product = Product::factory()->create(['slug' => 'linked-product']);

        ProductProvisioningRules::query()->create([
            'product_id' => $product->id,
            'node_group_id' => $group->id,
            'auto_provision' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.node-groups.show', $group))
            ->delete(route('admin.node-groups.destroy', $group))
            ->assertRedirect(route('admin.node-groups.show', $group))
            ->assertSessionHasErrors('group');

        $this->assertDatabaseHas('node_groups', [
            'id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    public function test_support_cannot_manage_groups(): void
    {
        $support = User::factory()->withRole('support')->create();
        $group = NodeGroup::factory()->create(['key' => 'support-blocked']);

        $this->actingAs($support)
            ->get(route('admin.node-groups.index'))
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('admin.node-groups.store'), [
                'name' => 'Blocked',
                'type' => NodeGroupType::General->value,
                'status' => NodeGroupStatus::Active->value,
            ])
            ->assertForbidden();

        $this->actingAs($support)
            ->put(route('admin.node-groups.update', $group), [
                'name' => 'Hacked',
                'type' => NodeGroupType::General->value,
                'status' => NodeGroupStatus::Active->value,
            ])
            ->assertForbidden();

        $this->actingAs($support)
            ->delete(route('admin.node-groups.destroy', $group))
            ->assertForbidden();
    }
}
