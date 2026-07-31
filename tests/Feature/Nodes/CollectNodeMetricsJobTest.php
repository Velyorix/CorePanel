<?php

namespace Tests\Feature\Nodes;

use Core\Nodes\Enums\NodeMetricStatus;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Jobs\CollectNodeMetricsJob;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeMetricsCollectionService;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CollectNodeMetricsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.nodes.metrics.enabled' => true,
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));
    }

    public function test_collection_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeMetricsCollectionService::class),
            app(NodeMetricsCollectionService::class),
        );
    }

    public function test_collects_metrics_for_monitorable_nodes(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'metrics-node.example.test',
            'status' => NodeStatus::Active,
            'max_services' => 50,
        ]);

        Node::factory()->create([
            'hostname' => 'no-module.example.test',
            'module' => null,
        ]);

        Node::factory()->forModule('stub')->create([
            'hostname' => 'disabled-node.example.test',
            'status' => NodeStatus::Disabled,
        ]);

        $result = app(NodeMetricsCollectionService::class)->collectAll();

        $this->assertSame(1, $result->collected);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, $result->failed);

        $this->assertDatabaseHas('node_metrics', [
            'node_id' => $node->id,
            'status' => NodeMetricStatus::Success->value,
            'current_services' => 0,
            'max_services' => 50,
            'cpu_usage' => 5.00,
            'ram_usage' => 8192.00,
            'disk_usage' => 120.00,
            'network_in' => 45.50,
            'network_out' => 22.00,
            'capacity_available' => 1,
        ]);

        $node->refresh();
        $this->assertSame(5, $node->capacityUsage()->cpuCores);
        $this->assertSame('metrics', $node->capacityUsage()->source);

        $this->assertDatabaseHas('node_metrics', [
            'status' => NodeMetricStatus::Skipped->value,
        ]);
    }

    public function test_records_failed_metric_when_provider_returns_error(): void
    {
        app(ProviderRegistry::class)->registerNode(new class implements NodeProviderInterface
        {
            public function key(): string
            {
                return 'metrics-fail';
            }

            public function label(): string
            {
                return 'Metrics Fail';
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
            {
                return NodeResourcesResponse::failed('Remote metrics endpoint unavailable.');
            }
        });

        $node = Node::factory()->forModule('metrics-fail')->create([
            'hostname' => 'metrics-fail.example.test',
        ]);

        $metric = app(NodeMetricsCollectionService::class)->collectForNode($node);

        $this->assertSame(NodeMetricStatus::Failed, $metric->status);
        $this->assertDatabaseHas('node_metrics', [
            'node_id' => $node->id,
            'status' => NodeMetricStatus::Failed->value,
        ]);
        $this->assertSame(
            'Remote metrics endpoint unavailable.',
            $metric->payload['message'] ?? null,
        );
    }

    public function test_job_delegates_to_collection_service(): void
    {
        $service = \Mockery::mock(NodeMetricsCollectionService::class);
        $service->shouldReceive('collectAll')
            ->once()
            ->andReturn(new \Core\Nodes\DataTransferObjects\NodeMetricsCollectionResult);

        app(CollectNodeMetricsJob::class)->handle($service);
    }

    public function test_collect_node_metrics_job_is_scheduled_every_five_minutes_by_default(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(CollectNodeMetricsJob::class, $output);
        $this->assertStringContainsString('*/5', $output);
    }

    public function test_collection_is_skipped_when_disabled_in_config(): void
    {
        config(['corepanel.nodes.metrics.enabled' => false]);

        Node::factory()->forModule('stub')->create([
            'hostname' => 'disabled-collection.example.test',
        ]);

        $result = app(NodeMetricsCollectionService::class)->collectAll();

        $this->assertSame(0, $result->total());
        $this->assertDatabaseCount('node_metrics', 0);
    }
}
