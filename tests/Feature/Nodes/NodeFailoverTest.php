<?php

namespace Tests\Feature\Nodes;

use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Jobs\ProcessNodeFailoverJob;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Services\NodeFailoverService;
use Core\Nodes\Services\NodeHealthCheckService;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NodeFailoverTest extends TestCase
{
    use RefreshDatabase;

    private NodeFailoverService $failover;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.nodes.failover.enabled' => true,
            'corepanel.nodes.failover.auto_reassign' => true,
            'corepanel.nodes.failover.reinstall_on_provider' => true,
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));

        $this->failover = app(NodeFailoverService::class);
    }

    public function test_failover_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeFailoverService::class),
            app(NodeFailoverService::class),
        );
    }

    public function test_reassigns_active_services_from_offline_node_to_healthy_peer(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'failover-group']);

        $failed = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'name' => 'Failed Node',
            'hostname' => 'failed.example.test',
            'status' => NodeStatus::Offline,
            'config' => [
                'health' => ['state' => NodeHealthState::Offline->value],
            ],
        ]);

        $replacement = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'name' => 'Replacement Node',
            'hostname' => 'replacement.example.test',
            'status' => NodeStatus::Active,
            'sort_order' => 1,
            'config' => [
                'health' => ['state' => NodeHealthState::Online->value],
            ],
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
            'status' => ServiceStatus::Active,
            'node_id' => $failed->id,
            'external_id' => 'stub-42',
        ]);

        Service::factory()->forProduct($product)->create([
            'module' => 'stub',
            'status' => ServiceStatus::Terminated,
            'node_id' => $failed->id,
        ]);

        $result = $this->failover->processNode($failed);

        $this->assertSame(1, $result->reassigned);
        $this->assertSame(0, $result->failed);
        $this->assertSame(0, $result->skipped);
        $this->assertSame($replacement->id, $service->fresh()->node_id);

        $this->assertDatabaseHas('node_logs', [
            'node_id' => $failed->id,
            'action' => 'node.failover.service_reassigned',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('node_logs', [
            'node_id' => $replacement->id,
            'action' => 'node.failover.service_received',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('service_actions_log', [
            'service_id' => $service->id,
            'action' => ServiceAction::Failover->value,
            'status' => 'success',
        ]);
    }

    public function test_marks_failover_failed_when_no_replacement_node_exists(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'solo-group']);

        $failed = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'solo-failed.example.test',
            'status' => NodeStatus::Offline,
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
        ]);

        $result = $this->failover->processNode($failed);

        $this->assertSame(0, $result->reassigned);
        $this->assertSame(1, $result->failed);
        $this->assertSame($failed->id, $service->fresh()->node_id);
        $this->assertDatabaseHas('service_actions_log', [
            'service_id' => $service->id,
            'action' => ServiceAction::Failover->value,
            'status' => 'failed',
        ]);
    }

    public function test_health_check_dispatches_failover_job_when_node_goes_offline(): void
    {
        Queue::fake();

        app(ProviderRegistry::class)->registerNode(new class implements NodeProviderInterface
        {
            public function key(): string
            {
                return 'failover-offline';
            }

            public function label(): string
            {
                return 'Failover Offline';
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::failed('Unreachable.');
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

        $node = Node::factory()->forModule('failover-offline')->create([
            'hostname' => 'dispatch-failover.example.test',
            'status' => NodeStatus::Active,
        ]);

        app(NodeHealthCheckService::class)->checkForNode($node);

        Queue::assertPushed(ProcessNodeFailoverJob::class, function (ProcessNodeFailoverJob $job) use ($node): bool {
            return $job->nodeId === $node->id;
        });
    }

    public function test_job_delegates_to_failover_service(): void
    {
        $service = \Mockery::mock(NodeFailoverService::class);
        $service->shouldReceive('processNode')
            ->once()
            ->andReturn(new \Core\Nodes\DataTransferObjects\NodeFailoverBatchResult);

        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'job-failover.example.test',
        ]);

        (new ProcessNodeFailoverJob($node->id))->handle($service);
    }

    public function test_failover_is_skipped_when_disabled_in_config(): void
    {
        config(['corepanel.nodes.failover.enabled' => false]);

        $group = NodeGroup::factory()->create(['key' => 'disabled-failover']);
        $failed = Node::factory()->forGroup($group)->forModule('stub')->create([
            'hostname' => 'disabled-failover.example.test',
            'status' => NodeStatus::Offline,
        ]);
        $replacement = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'replacement-disabled.example.test',
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
        ]);

        $result = $this->failover->processNode($failed);

        $this->assertSame(0, $result->total());
        $this->assertSame($failed->id, $service->fresh()->node_id);
        $this->assertNotSame($replacement->id, $service->fresh()->node_id);
    }
}
