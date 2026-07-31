<?php

namespace Tests\Feature\Nodes;

use App\Models\User;
use Core\Nodes\Enums\NodeClusterStatus;
use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeCluster;
use Core\Nodes\Models\NodeClusterMember;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Services\NodeClusterService;
use Core\Nodes\Services\NodeFailoverService;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NodeClusterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.node-clusters',
            'corepanel.nodes.clusters.prefer_peers_on_failover' => true,
            'corepanel.nodes.failover.enabled' => true,
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));
    }

    public function test_node_clusters_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('node_clusters'));
        $this->assertTrue(Schema::hasTable('node_cluster_members'));
    }

    public function test_cluster_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeClusterService::class),
            app(NodeClusterService::class),
        );
    }

    public function test_admin_can_create_cluster_with_members(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $nodeA = Node::factory()->forModule('stub')->create(['name' => 'Peer A']);
        $nodeB = Node::factory()->forModule('stub')->create(['name' => 'Peer B']);

        $this->actingAs($admin)
            ->post(route('admin.node-clusters.store'), [
                'name' => 'EU HA Cluster',
                'key' => 'eu-ha',
                'location' => 'Amsterdam',
                'description' => 'Primary HA peers',
                'status' => NodeClusterStatus::Active->value,
                'sort_order' => 1,
                'node_ids' => [$nodeA->id, $nodeB->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $cluster = NodeCluster::query()->where('key', 'eu-ha')->firstOrFail();

        $this->assertSame('EU HA Cluster', $cluster->name);
        $this->assertSame(2, $cluster->nodes()->count());
        $this->assertSame(2, NodeClusterMember::query()->where('node_cluster_id', $cluster->id)->count());
    }

    public function test_admin_can_update_and_delete_cluster(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $cluster = NodeCluster::factory()->create([
            'name' => 'Old Cluster',
            'key' => 'old-cluster',
        ]);
        $keep = Node::factory()->forModule('stub')->create(['name' => 'Keep Peer']);
        $add = Node::factory()->forModule('stub')->create(['name' => 'Add Peer']);

        NodeClusterMember::query()->create([
            'node_cluster_id' => $cluster->id,
            'node_id' => $keep->id,
            'sort_order' => 0,
            'weight' => 100,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.node-clusters.update', $cluster), [
                'name' => 'Updated Cluster',
                'key' => 'updated-cluster',
                'status' => NodeClusterStatus::Active->value,
                'sort_order' => 2,
                'node_ids' => [$keep->id, $add->id],
            ])
            ->assertRedirect(route('admin.node-clusters.show', $cluster))
            ->assertSessionHas('status');

        $cluster->refresh();
        $this->assertSame('Updated Cluster', $cluster->name);
        $this->assertSame(2, $cluster->nodes()->count());

        $this->actingAs($admin)
            ->delete(route('admin.node-clusters.destroy', $cluster))
            ->assertRedirect(route('admin.node-clusters.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted($cluster);
    }

    public function test_failover_prefers_healthy_cluster_peer_over_group_node(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'mixed-pool']);

        $failed = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'name' => 'Failed Cluster Node',
            'hostname' => 'failed-cluster.example.test',
            'status' => NodeStatus::Offline,
            'config' => [
                'health' => ['state' => NodeHealthState::Offline->value],
            ],
        ]);

        $clusterPeer = Node::factory()->forModule('stub')->withCapacity(10)->create([
            'name' => 'Cluster Peer',
            'hostname' => 'cluster-peer.example.test',
            'status' => NodeStatus::Active,
            'node_group_id' => null,
            'config' => [
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        $groupOnly = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'name' => 'Group Only Node',
            'hostname' => 'group-only.example.test',
            'status' => NodeStatus::Active,
            'sort_order' => 0,
            'config' => [
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        $cluster = NodeCluster::factory()->create(['key' => 'ha-peers']);
        app(NodeClusterService::class)->syncMembers($cluster, [$failed->id, $clusterPeer->id]);

        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('stub')
            ->withProvisioningRules([
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create([
            'module' => 'stub',
            'status' => ServiceStatus::Active,
            'node_id' => $failed->id,
            'external_id' => 'stub-failover-cluster',
        ]);

        $result = app(NodeFailoverService::class)->processNode($failed);

        $this->assertSame(1, $result->reassigned);
        $this->assertSame($clusterPeer->id, $service->fresh()->node_id);
        $this->assertNotSame($groupOnly->id, $service->fresh()->node_id);
    }

    public function test_navigation_includes_clusters_item(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $sections = app(\Core\Admin\Navigation\AdminNavigation::class)->forUser($admin);

        $clustersItem = collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Clusters'));

        $this->assertNotNull($clustersItem);
        $this->assertFalse($clustersItem['placeholder']);
        $this->assertSame(route('admin.node-clusters.index'), $clustersItem['url']);
    }
}
