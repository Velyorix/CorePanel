<?php

namespace Tests\Feature\Nodes;

use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeCapacityService;
use Core\Nodes\Services\NodeMetricsCollectionService;
use Core\Nodes\Services\NodeService;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Providers\Services\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeCapacityTrackingTest extends TestCase
{
    use RefreshDatabase;

    private NodeCapacityService $capacity;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));

        $this->capacity = app(NodeCapacityService::class);
    }

    public function test_capacity_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeCapacityService::class),
            app(NodeCapacityService::class),
        );
    }

    public function test_apply_resources_persists_realtime_usage_snapshot(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'capacity-track.example.test',
        ]);

        $updated = $this->capacity->applyResources($node, new NodeResourcesData(
            maxServices: 50,
            currentServices: 3,
            cpuUsage: 4.0,
            ramUsage: 4096.0,
            diskUsage: 80.0,
            networkIn: 45.5,
            networkOut: 22.0,
            loadAverage: 1.25,
            capacityAvailable: true,
        ), 'metrics');

        $usage = $updated->capacityUsage();

        $this->assertSame(4, $usage->cpuCores);
        $this->assertSame(4096, $usage->ramMb);
        $this->assertSame(80, $usage->diskGb);
        $this->assertSame(3, $usage->services);
        $this->assertSame(45.5, $usage->bandwidthInMbps);
        $this->assertSame(22.0, $usage->bandwidthOutMbps);
        $this->assertSame(1.25, $usage->loadAverage);
        $this->assertTrue($usage->capacityAvailable);
        $this->assertSame('metrics', $usage->source);
        $this->assertNotNull($usage->syncedAt);
        $this->assertSame(4, data_get($updated->config, 'capacity.allocated.cpu_cores'));
    }

    public function test_metrics_collection_updates_node_capacity_snapshot(): void
    {
        $node = Node::factory()->forModule('stub')->withCapacityLimits([
            'max_services' => 50,
            'max_cpu_cores' => 16,
            'max_ram_mb' => 65536,
            'max_disk_gb' => 1000,
            'max_bandwidth_mbps' => 1000,
        ])->create([
            'hostname' => 'metrics-capacity.example.test',
        ]);

        app(NodeMetricsCollectionService::class)->collectForNode($node);

        $node->refresh();
        $usage = $node->capacityUsage();

        $this->assertSame(5, $usage->cpuCores);
        $this->assertSame(8192, $usage->ramMb);
        $this->assertSame(120, $usage->diskGb);
        $this->assertSame(45.5, $usage->bandwidthInMbps);
        $this->assertSame(22.0, $usage->bandwidthOutMbps);
        $this->assertSame('metrics', $usage->source);
        $this->assertTrue($this->capacity->isUsageFresh($node));
    }

    public function test_node_sync_updates_capacity_snapshot(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'sync-capacity.example.test',
        ]);

        app(NodeService::class)->sync($node);

        $node->refresh();
        $usage = $node->capacityUsage();

        $this->assertSame(5, $usage->cpuCores);
        $this->assertSame(8192, $usage->ramMb);
        $this->assertSame('sync', $usage->source);
    }

    public function test_has_resource_capacity_respects_bandwidth_limit(): void
    {
        $node = Node::factory()->withCapacityLimits([
            'max_bandwidth_mbps' => 100,
        ])->create([
            'config' => [
                'capacity' => [
                    'usage' => [
                        'bandwidth_in_mbps' => 90,
                        'bandwidth_out_mbps' => 95,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($node->hasResourceCapacity());

        $node = Node::factory()->withCapacityLimits([
            'max_bandwidth_mbps' => 100,
        ])->create([
            'config' => [
                'capacity' => [
                    'usage' => [
                        'bandwidth_in_mbps' => 120,
                        'bandwidth_out_mbps' => 10,
                    ],
                ],
            ],
        ]);

        $this->assertFalse($node->hasResourceCapacity());
    }

    public function test_has_resource_capacity_respects_provider_capacity_flag(): void
    {
        $node = Node::factory()->create([
            'config' => [
                'capacity' => [
                    'usage' => [
                        'capacity_available' => false,
                    ],
                ],
            ],
        ]);

        $this->assertFalse($node->hasResourceCapacity());
    }
}
