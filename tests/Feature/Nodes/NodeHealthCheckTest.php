<?php

namespace Tests\Feature\Nodes;

use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Jobs\RunNodeHealthChecksJob;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeHealthCheckService;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Providers\Services\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NodeHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private NodeHealthCheckService $healthChecks;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.nodes.health.enabled' => true,
            'corepanel.nodes.health.auto_status' => true,
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));

        $this->healthChecks = app(NodeHealthCheckService::class);
    }

    public function test_node_health_checks_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('node_health_checks'));
    }

    public function test_health_check_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeHealthCheckService::class),
            app(NodeHealthCheckService::class),
        );
    }

    public function test_records_online_state_for_healthy_node(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'healthy.example.test',
            'status' => NodeStatus::Active,
        ]);

        $check = $this->healthChecks->checkForNode($node);

        $this->assertSame(NodeHealthState::Online, $check->state);
        $this->assertDatabaseHas('node_health_checks', [
            'node_id' => $node->id,
            'state' => NodeHealthState::Online->value,
        ]);

        $node->refresh();
        $this->assertSame(NodeHealthState::Online, $node->healthState());
        $this->assertSame(NodeStatus::Active, $node->status);
    }

    public function test_marks_node_offline_when_connection_fails_and_updates_status(): void
    {
        app(ProviderRegistry::class)->registerNode(new class implements NodeProviderInterface
        {
            public function key(): string
            {
                return 'health-fail';
            }

            public function label(): string
            {
                return 'Health Fail';
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::failed('Remote API unreachable.');
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
            {
                return NodeResourcesResponse::success(new NodeResourcesData);
            }
        });

        $node = Node::factory()->forModule('health-fail')->create([
            'hostname' => 'offline.example.test',
            'status' => NodeStatus::Active,
        ]);

        $check = $this->healthChecks->checkForNode($node);

        $this->assertSame(NodeHealthState::Offline, $check->state);
        $this->assertSame(NodeStatus::Offline, $node->fresh()->status);
        $this->assertTrue($node->fresh()->healthSnapshot()->autoManaged);

        $this->assertDatabaseHas('node_logs', [
            'node_id' => $node->id,
            'action' => 'node.health.status_changed',
            'status' => 'success',
        ]);
    }

    public function test_recovers_auto_managed_offline_node_when_health_returns_online(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'recover.example.test',
            'status' => NodeStatus::Offline,
            'config' => [
                'health' => [
                    'state' => NodeHealthState::Offline->value,
                    'auto_managed' => true,
                ],
            ],
        ]);

        $this->healthChecks->checkForNode($node);

        $node->refresh();
        $this->assertSame(NodeHealthState::Online, $node->healthState());
        $this->assertSame(NodeStatus::Active, $node->status);
        $this->assertFalse($node->healthSnapshot()->autoManaged);
    }

    public function test_marks_node_degraded_when_capacity_is_unavailable(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'degraded.example.test',
            'status' => NodeStatus::Active,
            'config' => [
                'capacity' => [
                    'usage' => [
                        'cpu_cores' => 2,
                        'capacity_available' => false,
                        'synced_at' => now()->toIso8601String(),
                    ],
                    'available' => false,
                    'synced_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $check = $this->healthChecks->checkForNode($node);

        $this->assertSame(NodeHealthState::Degraded, $check->state);
        $this->assertSame(NodeStatus::Active, $node->fresh()->status);
    }

    public function test_skips_disabled_nodes_without_changing_status(): void
    {
        $node = Node::factory()->forModule('stub')->disabled()->create([
            'hostname' => 'disabled-health.example.test',
        ]);

        $check = $this->healthChecks->checkForNode($node);

        $this->assertSame(NodeHealthState::Skipped, $check->state);
        $this->assertSame(NodeStatus::Disabled, $node->fresh()->status);
    }

    public function test_check_all_skips_nodes_without_module(): void
    {
        Node::factory()->forModule('stub')->create([
            'hostname' => 'check-all.example.test',
        ]);

        Node::factory()->create([
            'hostname' => 'no-module.example.test',
            'module' => null,
        ]);

        $result = $this->healthChecks->checkAll();

        $this->assertSame(1, $result->online);
        $this->assertSame(0, $result->offline);
        $this->assertDatabaseCount('node_health_checks', 1);
    }

    public function test_job_delegates_to_health_check_service(): void
    {
        $service = \Mockery::mock(NodeHealthCheckService::class);
        $service->shouldReceive('checkAll')
            ->once()
            ->andReturn(new \Core\Nodes\DataTransferObjects\NodeHealthCheckBatchResult);

        app(RunNodeHealthChecksJob::class)->handle($service);
    }

    public function test_run_node_health_checks_job_is_scheduled_every_minute_by_default(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(RunNodeHealthChecksJob::class, $output);
        $this->assertDoesNotMatchRegularExpression(
            '/\\*\\/5.*RunNodeHealthChecksJob/',
            $output,
        );
    }

    public function test_health_checks_are_skipped_when_disabled_in_config(): void
    {
        config(['corepanel.nodes.health.enabled' => false]);

        Node::factory()->forModule('stub')->create([
            'hostname' => 'disabled-checks.example.test',
        ]);

        $result = $this->healthChecks->checkAll();

        $this->assertSame(0, $result->total());
        $this->assertDatabaseCount('node_health_checks', 0);
    }
}
