<?php

namespace Tests\Feature\Servers;

use Core\Nodes\DataTransferObjects\NodeData;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeService;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\Stubs\StubServerProvider;
use Core\Providers\Services\ProviderRegistry;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeCapacityTest extends TestCase
{
    use RefreshDatabase;

    private NodeService $nodeService;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));

        $this->nodeService = app(NodeService::class);
    }

    public function test_can_persist_capacity_limits_on_create(): void
    {
        $node = $this->nodeService->create(NodeData::fromArray([
            'name' => 'Capacity Node',
            'hostname' => 'capacity.example.test',
            'type' => NodeType::Vps->value,
            'module' => 'stub',
            'max_services' => 40,
            'max_cpu_cores' => 16,
            'max_ram_mb' => 65536,
            'max_disk_gb' => 2000,
        ]));

        $this->assertSame(40, $node->max_services);
        $this->assertSame(16, $node->max_cpu_cores);
        $this->assertSame(65536, $node->max_ram_mb);
        $this->assertSame(2000, $node->max_disk_gb);
        $this->assertTrue($node->hasConfiguredCapacityLimits());
    }

    public function test_connection_request_includes_capacity_limits(): void
    {
        $node = Node::factory()->forModule('stub')->withCapacityLimits([
            'max_services' => 25,
            'max_cpu_cores' => 8,
            'max_ram_mb' => 32768,
            'max_disk_gb' => 500,
        ])->create([
            'hostname' => 'limits.example.test',
        ]);

        $request = $node->toConnectionRequest();

        $this->assertInstanceOf(NodeConnectionRequest::class, $request);
        $this->assertSame(25, $request->maxServices);
        $this->assertSame(8, $request->maxCpuCores);
        $this->assertSame(32768, $request->maxRamMb);
        $this->assertSame(500, $request->maxDiskGb);
    }

    public function test_has_capacity_respects_service_and_resource_limits(): void
    {
        $node = Node::factory()->withCapacityLimits([
            'max_services' => 2,
            'max_cpu_cores' => 8,
            'max_ram_mb' => 16384,
            'max_disk_gb' => 250,
        ])->create([
            'config' => [
                'capacity' => [
                    'allocated' => [
                        'cpu_cores' => 6,
                        'ram_mb' => 12000,
                        'disk_gb' => 100,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($node->hasCapacity());
        $this->assertTrue($node->hasServiceCapacity());
        $this->assertTrue($node->hasResourceCapacity());

        Service::factory()->count(2)->create([
            'node_id' => $node->id,
            'status' => ServiceStatus::Active,
        ]);

        $node->refresh();
        $this->assertFalse($node->hasServiceCapacity());
        $this->assertFalse($node->hasCapacity());

        $node = Node::factory()->withCapacityLimits([
            'max_cpu_cores' => 4,
        ])->create([
            'config' => [
                'capacity' => [
                    'allocated' => [
                        'cpu_cores' => 4,
                    ],
                ],
            ],
        ]);

        $this->assertFalse($node->hasResourceCapacity());
        $this->assertFalse($node->hasCapacity());
    }

    public function test_null_limits_mean_unlimited_resources(): void
    {
        $node = Node::factory()->create([
            'max_services' => null,
            'max_cpu_cores' => null,
            'max_ram_mb' => null,
            'max_disk_gb' => null,
            'config' => [
                'capacity' => [
                    'allocated' => [
                        'cpu_cores' => 999,
                        'ram_mb' => 999999,
                        'disk_gb' => 99999,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($node->hasResourceCapacity());
        $this->assertFalse($node->hasConfiguredCapacityLimits());
    }
}
