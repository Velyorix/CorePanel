<?php

namespace Tests\Feature\Nodes;

use App\Models\User;
use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Enums\NodeMetricStatus;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Jobs\RunNodeHealthChecksJob;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Models\NodeMetric;
use Core\Nodes\Services\NodeAllocationAlgorithm;
use Core\Nodes\Services\NodeConnectionTestService;
use Core\Nodes\Services\NodeFailoverService;
use Core\Nodes\Services\NodeHealthCheckService;
use Core\Nodes\Services\NodeLoadBalancingService;
use Core\Nodes\Services\NodeMonitoringService;
use Core\Nodes\Services\NodeOverloadService;
use Core\Nodes\Services\NodeTelemetryService;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Provisioning\Exceptions\NodeProvisioningDeferredException;
use Core\Provisioning\Services\NodeSelectionService;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * End-to-end nodes infrastructure coverage across health, selection, failover,
 * monitoring, load balancing, overload protection, and security pre-checks.
 */
class NodeInfrastructureFeatureTest extends TestCase
{
    use RefreshDatabase;

    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.node-infrastructure',
            'corepanel.provisioning.stub.enabled' => true,
            'corepanel.provisioning.require_node_for_assigned_group' => true,
            'corepanel.nodes.health.enabled' => true,
            'corepanel.nodes.health.auto_status' => true,
            'corepanel.nodes.failover.enabled' => true,
            'corepanel.nodes.failover.auto_reassign' => true,
            'corepanel.nodes.allocation.require_credentials' => false,
            'corepanel.nodes.allocation.load_balancing.enabled' => false,
            'corepanel.nodes.overload.enabled' => true,
            'corepanel.nodes.overload.defer_on_overload' => true,
            'corepanel.nodes.security.tls.required' => false,
            'corepanel.nodes.security.ip_whitelist.enabled' => false,
            'corepanel.nodes.monitoring.history_hours' => 24,
            'corepanel.nodes.monitoring.bucket_minutes' => 60,
        ]);

        Cache::flush();

        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();
        $this->registry->registerServer(app(StubServerProvider::class));
        $this->registry->registerNode(app(StubNodeProvider::class));
    }

    public function test_infrastructure_schema_tables_exist(): void
    {
        foreach ([
            'nodes',
            'node_groups',
            'node_group_relations',
            'node_logs',
            'node_metrics',
            'node_health_checks',
            'node_clusters',
            'node_cluster_members',
        ] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_core_node_services_are_registered_as_singletons(): void
    {
        foreach ([
            NodeAllocationAlgorithm::class,
            NodeLoadBalancingService::class,
            NodeOverloadService::class,
            NodeHealthCheckService::class,
            NodeFailoverService::class,
            NodeMonitoringService::class,
            NodeTelemetryService::class,
            NodeConnectionTestService::class,
            NodeSelectionService::class,
        ] as $service) {
            $this->assertSame(app($service), app($service), "Expected {$service} to be a singleton.");
        }
    }

    public function test_health_check_detects_offline_node_and_updates_status(): void
    {
        $this->registry->registerNode(new class implements NodeProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Failing Stub';
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::failed('Connection refused');
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::failed('Connection refused');
            }

            public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
            {
                return NodeResourcesResponse::failed('Connection refused');
            }
        });

        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'offline-node.example.test',
            'status' => NodeStatus::Active,
        ]);

        app(NodeHealthCheckService::class)->checkForNode($node);

        $node->refresh();

        $this->assertSame(NodeHealthState::Offline, $node->healthState());
        $this->assertSame(NodeStatus::Offline, $node->status);
        $this->assertDatabaseHas('node_health_checks', [
            'node_id' => $node->id,
            'state' => NodeHealthState::Offline->value,
        ]);
    }

    public function test_health_check_job_processes_fleet(): void
    {
        Node::factory()->forModule('stub')->count(2)->create();

        RunNodeHealthChecksJob::dispatchSync();

        $this->assertSame(2, \Core\Nodes\Models\NodeHealthCheck::query()->count());
    }

    public function test_node_selection_assigns_least_loaded_eligible_node(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'selection-group']);

        $busy = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'busy.example.test',
        ]);
        $free = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'free.example.test',
            'sort_order' => 1,
        ]);

        Service::factory()->count(4)->create([
            'module' => 'stub',
            'node_id' => $busy->id,
            'status' => ServiceStatus::Active,
        ]);

        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('stub')
            ->withProvisioningRules([
                'auto_provision' => true,
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
            'node_id' => null,
        ]);

        $selection = app(NodeSelectionService::class)->assignToService($service);

        $this->assertSame($free->id, $selection->nodeId());
        $this->assertSame($free->id, $service->fresh()->node_id);
    }

    public function test_failover_reassigns_services_from_offline_node(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'infra-failover']);

        $failed = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'failed.example.test',
            'status' => NodeStatus::Offline,
            'config' => [
                'health' => ['state' => NodeHealthState::Offline->value],
            ],
        ]);

        $replacement = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'replacement.example.test',
            'sort_order' => 1,
            'config' => [
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        $product = Product::factory()
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
            'external_id' => 'stub-service-1',
        ]);

        $result = app(NodeFailoverService::class)->processNode($failed);

        $this->assertSame(1, $result->reassigned);
        $this->assertSame($replacement->id, $service->fresh()->node_id);
        $this->assertDatabaseHas('node_logs', [
            'node_id' => $failed->id,
            'action' => 'node.failover.service_reassigned',
        ]);
    }

    public function test_monitoring_dashboard_exposes_live_metrics(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $node = Node::factory()->forModule('stub')->create([
            'name' => 'Live Metrics Node',
            'hostname' => 'metrics-live.example.test',
            'config' => [
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        NodeMetric::factory()->forNode($node)->create([
            'cpu_usage' => 42.5,
            'ram_usage' => 4096,
            'disk_usage' => 128,
            'load_average' => 1.25,
            'status' => NodeMetricStatus::Success,
            'collected_at' => now()->subMinutes(15),
        ]);

        $view = app(NodeMonitoringService::class)->forNode($node);

        $this->assertSame(42.5, $view->latestCpu);
        $this->assertTrue($view->charts[0]->hasData());

        $this->actingAs($admin)
            ->get(route('admin.nodes.monitoring'))
            ->assertOk()
            ->assertSee('Live Metrics Node')
            ->assertSee('<polyline', false);
    }

    public function test_provisioning_engine_assigns_node_before_activation(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'provision-group']);
        $node = Node::factory()->forGroup($group)->forModule('stub')->create([
            'hostname' => 'provision-target.example.test',
        ]);

        $product = Product::factory()
            ->withModule('stub')
            ->withProvisioningRules([
                'auto_provision' => true,
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
            'node_id' => null,
        ]);

        $response = app(ProvisioningEngine::class)->provision($service);

        $this->assertSame(ProviderOperationStatus::Success, $response->status);
        $this->assertSame($node->id, $service->fresh()->node_id);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
    }

    public function test_overload_protection_defers_provisioning_when_pool_is_saturated(): void
    {
        config([
            'corepanel.nodes.overload.enabled' => true,
            'corepanel.nodes.overload.queue_delay_seconds' => 30,
        ]);

        $group = NodeGroup::factory()->create(['key' => 'saturated-pool']);

        $node = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(1)->create([
            'hostname' => 'saturated.example.test',
        ]);

        Service::factory()->create([
            'module' => 'stub',
            'node_id' => $node->id,
            'status' => ServiceStatus::Active,
        ]);

        $product = Product::factory()
            ->withModule('stub')
            ->withProvisioningRules([
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $this->expectException(NodeProvisioningDeferredException::class);

        app(NodeSelectionService::class)->selectForService($service);
    }

    public function test_security_pre_check_blocks_insecure_api_url(): void
    {
        config(['corepanel.nodes.security.tls.required' => true]);

        $response = app(NodeConnectionTestService::class)->testPayload([
            'hostname' => 'secure.example.test',
            'module' => 'stub',
            'api_url' => 'http://insecure.example.test/api',
        ]);

        $this->assertSame(ProviderOperationStatus::Failed, $response->status);
        $this->assertStringContainsString('HTTPS', (string) $response->message);
    }

    public function test_nodes_telemetry_command_runs_without_error(): void
    {
        Node::factory()->forModule('stub')->create([
            'hostname' => 'telemetry-cli.example.test',
        ]);

        $this->artisan('nodes:telemetry')
            ->assertSuccessful();
    }
}
