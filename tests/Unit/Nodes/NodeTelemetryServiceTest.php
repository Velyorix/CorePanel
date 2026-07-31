<?php

namespace Tests\Unit\Nodes;

use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeHealthCheckService;
use Core\Nodes\Services\NodeMetricsCollectionService;
use Core\Nodes\Services\NodeTelemetryService;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Providers\Services\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NodeTelemetryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['corepanel.provisioning.stub.enabled' => true]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_refresh_runs_health_check_and_metrics_collection(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'telemetry.example.test',
        ]);

        $health = Mockery::mock(NodeHealthCheckService::class);
        $health->shouldReceive('checkForNode')
            ->once()
            ->with(Mockery::on(fn (Node $matched): bool => $matched->is($node)));

        $metrics = Mockery::mock(NodeMetricsCollectionService::class);
        $metrics->shouldReceive('collectForNode')
            ->once()
            ->with(Mockery::type(Node::class));

        $service = new NodeTelemetryService(app(ProviderRegistry::class), $health, $metrics);
        $service->refresh($node);
    }

    public function test_create_node_bootstraps_health_state(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'bootstrapped.example.test',
        ]);

        app(NodeTelemetryService::class)->refresh($node);

        $node->refresh();

        $this->assertSame(NodeHealthState::Online, $node->healthState());
        $this->assertDatabaseHas('node_health_checks', [
            'node_id' => $node->id,
            'state' => NodeHealthState::Online->value,
        ]);
        $this->assertDatabaseHas('node_metrics', [
            'node_id' => $node->id,
        ]);
    }
}
