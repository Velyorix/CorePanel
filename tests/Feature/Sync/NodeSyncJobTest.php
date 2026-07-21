<?php

namespace Tests\Feature\Sync;

use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Models\Node;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Sync\Jobs\NodeSyncJob;
use Core\Sync\Services\NodeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NodeSyncJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.nodes.sync.enabled' => true,
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));
    }

    public function test_node_sync_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeSyncService::class),
            app(NodeSyncService::class),
        );
    }

    public function test_syncs_nodes_with_registered_provider_modules(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'sync-node.example.test',
            'status' => NodeStatus::Active,
        ]);

        Node::factory()->create([
            'hostname' => 'no-module.example.test',
            'module' => null,
        ]);

        Node::factory()->forModule('stub')->create([
            'hostname' => 'disabled-node.example.test',
            'status' => NodeStatus::Disabled,
        ]);

        $result = app(NodeSyncService::class)->syncAll();

        $this->assertSame(1, $result->synced);
        $this->assertSame(0, $result->failed);
        $this->assertSame(1, $result->skipped);

        $node->refresh();
        $this->assertSame('metrics', $node->capacityUsage()->source);
    }

    public function test_records_failed_sync_without_throwing(): void
    {
        app(ProviderRegistry::class)->registerNode(new class implements NodeProviderInterface
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
                return NodeOperationResponse::success();
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::failed('Remote sync endpoint unavailable.');
            }

            public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
            {
                return NodeResourcesResponse::failed('not used');
            }
        });

        Node::factory()->forModule('stub')->create([
            'hostname' => 'sync-fail.example.test',
        ]);

        $result = app(NodeSyncService::class)->syncAll();

        $this->assertSame(0, $result->synced);
        $this->assertSame(1, $result->failed);
    }

    public function test_job_delegates_to_node_sync_service(): void
    {
        $service = \Mockery::mock(NodeSyncService::class);
        $service->shouldReceive('syncAll')
            ->once()
            ->andReturn(new \Core\Sync\DataTransferObjects\NodeSyncResult);

        app(NodeSyncJob::class)->handle($service);
    }

    public function test_node_sync_job_is_scheduled_every_ten_minutes_by_default(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(NodeSyncJob::class, $output);
        $this->assertStringContainsString('*/10', $output);
    }

    public function test_sync_is_skipped_when_disabled_in_config(): void
    {
        config(['corepanel.nodes.sync.enabled' => false]);

        Node::factory()->forModule('stub')->create([
            'hostname' => 'disabled-sync.example.test',
        ]);

        $result = app(NodeSyncService::class)->syncAll();

        $this->assertSame(0, $result->total());
    }

    public function test_nodes_sync_command_syncs_single_node(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'cli-sync.example.test',
        ]);

        $this->artisan('nodes:sync', ['node' => (string) $node->id])
            ->assertSuccessful()
            ->expectsOutputToContain('synced');

        $node->refresh();
        $this->assertSame('metrics', $node->capacityUsage()->source);
    }
}
