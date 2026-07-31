<?php

namespace Tests\Feature\Servers;

use App\Models\User;
use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Models\NodeGroupRelation;
use Core\Nodes\Services\NodeLogService;
use Core\Nodes\Services\NodeService;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end servers registry coverage: groups, nodes, credentials,
 * connection tests, sync, capacity, logs, permissions, and lifecycle guards.
 */
class ServersRegistryFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.servers-registry',
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));
    }

    public function test_registry_services_resolve_from_container(): void
    {
        $this->assertSame(app(NodeService::class), app(NodeService::class));
        $this->assertInstanceOf(NodeLogService::class, app(NodeLogService::class));
    }

    public function test_admin_registry_workflow_covers_groups_nodes_connection_sync_and_logs(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.node-groups.store'), [
                'name' => 'Registry EU Pool',
                'key' => 'registry-eu',
                'location' => 'Frankfurt',
                'type' => NodeGroupType::Game->value,
                'description' => 'End-to-end registry pool',
                'status' => NodeGroupStatus::Active->value,
                'sort_order' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $group = NodeGroup::query()->where('key', 'registry-eu')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.nodes.test-connection'), [
                'name' => 'Registry Node',
                'hostname' => 'registry-node.example.test',
                'module' => 'stub',
                'credentials' => ['api_key' => 'draft-key'],
            ])
            ->assertRedirect()
            ->assertSessionHas('connection_status');

        $this->actingAs($admin)
            ->post(route('admin.nodes.store'), [
                'name' => 'Registry Node',
                'hostname' => 'registry-node.example.test',
                'type' => NodeType::Game->value,
                'module' => 'stub',
                'ip_address' => '203.0.113.88',
                'api_url' => 'https://panel.registry.example.test',
                'status' => NodeStatus::Active->value,
                'max_services' => 30,
                'max_cpu_cores' => 8,
                'max_ram_mb' => 32768,
                'max_disk_gb' => 500,
                'node_group_id' => $group->id,
                'credentials' => ['api_key' => 'registry-secret-key'],
            ])
            ->assertRedirect(route('admin.nodes.index'))
            ->assertSessionHas('status');

        $node = Node::query()->where('hostname', 'registry-node.example.test')->firstOrFail();

        $this->assertSame($group->id, $node->node_group_id);
        $this->assertSame(1, NodeGroupRelation::query()->where('node_id', $node->id)->count());
        $this->assertTrue((bool) NodeGroupRelation::query()
            ->where('node_id', $node->id)
            ->where('node_group_id', $group->id)
            ->value('is_primary'));

        $rawCredentials = DB::table('nodes')->where('id', $node->id)->value('credentials');
        $this->assertStringNotContainsString('registry-secret-key', (string) $rawCredentials);

        $this->actingAs($admin)
            ->get(route('admin.node-groups.show', $group))
            ->assertOk()
            ->assertSee('Registry EU Pool')
            ->assertSee('Registry Node')
            ->assertSee(__('Primary'));

        $this->actingAs($admin)
            ->get(route('admin.nodes.index'))
            ->assertOk()
            ->assertSee('Registry Node')
            ->assertSee('Registry EU Pool');

        $this->actingAs($admin)
            ->get(route('admin.nodes.show', $node))
            ->assertOk()
            ->assertSee('Registry Node')
            ->assertSee('Registry EU Pool')
            ->assertSee('••••••')
            ->assertDontSee('registry-secret-key');

        $this->actingAs($admin)
            ->from(route('admin.nodes.show', $node))
            ->post(route('admin.nodes.test-connection.node', $node))
            ->assertRedirect(route('admin.nodes.show', $node))
            ->assertSessionHas('connection_status');

        $this->actingAs($admin)
            ->post(route('admin.nodes.sync', $node))
            ->assertRedirect(route('admin.nodes.show', $node))
            ->assertSessionHas('status');

        $node->refresh();
        $this->assertSame(5, data_get($node->config, 'capacity.allocated.cpu_cores'));

        $this->assertDatabaseHas('node_logs', [
            'node_id' => $node->id,
            'action' => 'node.created',
            'status' => 'success',
            'performed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('node_logs', [
            'node_id' => $node->id,
            'action' => 'node.sync',
            'status' => 'success',
            'performed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.nodes.show', $node))
            ->assertOk()
            ->assertSee('node.created')
            ->assertSee('node.sync');

        $this->actingAs($admin)
            ->delete(route('admin.nodes.destroy', $node))
            ->assertRedirect(route('admin.nodes.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('nodes', ['id' => $node->id]);
        $this->assertSame(0, NodeGroupRelation::query()->where('node_id', $node->id)->count());

        $this->actingAs($admin)
            ->delete(route('admin.node-groups.destroy', $group))
            ->assertRedirect(route('admin.node-groups.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted($group);
    }

    public function test_nodes_index_filters_by_type_status_module_and_group(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $group = NodeGroup::factory()->create(['key' => 'filter-pool']);

        Node::factory()->forModule('stub')->create([
            'name' => 'Filter Match Node',
            'hostname' => 'filter-match.example.test',
            'type' => NodeType::Vps,
            'status' => NodeStatus::Active,
            'node_group_id' => $group->id,
        ]);

        Node::factory()->forModule('stub')->create([
            'name' => 'Filter Other Node',
            'hostname' => 'filter-other.example.test',
            'type' => NodeType::Game,
            'status' => NodeStatus::Disabled,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.nodes.index', [
                'q' => 'filter-match',
                'type' => NodeType::Vps->value,
                'status' => NodeStatus::Active->value,
                'module' => 'stub',
                'node_group_id' => $group->id,
            ]))
            ->assertOk()
            ->assertSee('Filter Match Node')
            ->assertDontSee('Filter Other Node');
    }

    public function test_admin_cannot_delete_node_with_allocated_services(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'blocked-delete.example.test',
        ]);

        Service::factory()->create([
            'node_id' => $node->id,
            'status' => ServiceStatus::Active,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.nodes.show', $node))
            ->delete(route('admin.nodes.destroy', $node))
            ->assertRedirect(route('admin.nodes.show', $node))
            ->assertSessionHasErrors('node');

        $this->assertDatabaseHas('nodes', [
            'id' => $node->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('node_logs', [
            'node_id' => $node->id,
            'action' => 'node.delete_failed',
            'status' => 'failed',
            'performed_by' => $admin->id,
        ]);
    }

    public function test_sync_failure_records_failed_log_and_keeps_node_active(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        app(ProviderRegistry::class)->registerNode(new class implements NodeProviderInterface
        {
            public function key(): string
            {
                return 'sync-fail';
            }

            public function label(): string
            {
                return 'Sync Fail';
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::failed('Remote sync rejected the request.');
            }

            public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
            {
                return NodeResourcesResponse::success(
                    new NodeResourcesData(maxServices: 1, currentServices: 0, capacityAvailable: true),
                );
            }
        });

        $node = Node::factory()->forModule('sync-fail')->create([
            'hostname' => 'sync-fail.example.test',
            'status' => NodeStatus::Active,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.nodes.show', $node))
            ->post(route('admin.nodes.sync', $node))
            ->assertRedirect(route('admin.nodes.show', $node))
            ->assertSessionHasErrors('node');

        $this->assertSame(NodeStatus::Active, $node->fresh()->status);
        $this->assertDatabaseHas('node_logs', [
            'node_id' => $node->id,
            'action' => 'node.sync',
            'status' => 'failed',
            'performed_by' => $admin->id,
        ]);
    }

    public function test_guest_is_redirected_from_servers_registry_admin(): void
    {
        $group = NodeGroup::factory()->create();
        $node = Node::factory()->forModule('stub')->create();

        $this->get(route('admin.nodes.index'))->assertRedirect(route('login'));
        $this->get(route('admin.nodes.show', $node))->assertRedirect(route('login'));
        $this->get(route('admin.node-groups.index'))->assertRedirect(route('login'));
        $this->get(route('admin.node-groups.show', $group))->assertRedirect(route('login'));
    }
}
