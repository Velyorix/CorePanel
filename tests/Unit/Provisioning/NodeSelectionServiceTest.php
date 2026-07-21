<?php

namespace Tests\Unit\Provisioning;

use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\Exceptions\NoEligibleNodeException;
use Core\Provisioning\Services\NodeSelectionService;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private NodeSelectionService $selection;

    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->selection = app(NodeSelectionService::class);
        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();

        config(['corepanel.provisioning.require_node_for_assigned_group' => true]);
    }

    public function test_selection_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeSelectionService::class),
            app(NodeSelectionService::class),
        );
    }

    public function test_select_returns_empty_when_product_has_no_node_group(): void
    {
        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $result = $this->selection->selectForService($service);

        $this->assertFalse($result->hasGroup());
        $this->assertFalse($result->hasNode());
    }

    public function test_assign_picks_least_allocated_node_in_group(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'eu-game']);
        $busy = Node::factory()->forGroup($group)->forModule('pterodactyl')->withCapacity(10)->create([
            'name' => 'busy',
            'hostname' => 'busy.example.test',
        ]);
        $free = Node::factory()->forGroup($group)->forModule('pterodactyl')->withCapacity(10)->create([
            'name' => 'free',
            'hostname' => 'free.example.test',
            'sort_order' => 1,
        ]);

        Service::factory()->count(3)->create([
            'module' => 'pterodactyl',
            'node_id' => $busy->id,
            'status' => ServiceStatus::Active,
        ]);

        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('pterodactyl')
            ->withProvisioningRules([
                'auto_provision' => true,
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create([
            'module' => 'pterodactyl',
            'status' => ServiceStatus::Pending,
            'node_id' => null,
        ]);

        $result = $this->selection->assignToService($service);

        $this->assertTrue($result->hasNode());
        $this->assertSame($free->id, $result->nodeId());
        $this->assertSame($free->id, $service->fresh()->node_id);
        $this->assertInstanceOf(NodeConnectionRequest::class, $result->connection);
        $this->assertSame('free.example.test', $result->connection?->hostname);
    }

    public function test_select_throws_when_group_has_no_eligible_node(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'empty-group']);

        $product = Product::factory()
            ->withModule('stub')
            ->withProvisioningRules([
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create([
            'module' => 'stub',
        ]);

        $this->expectException(NoEligibleNodeException::class);
        $this->expectExceptionMessage('No eligible node found for node group [empty-group]');

        $this->selection->selectForService($service);
    }

    public function test_select_skips_disabled_and_full_nodes(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'filtered']);

        Node::factory()->forGroup($group)->forModule('stub')->disabled()->create();
        Node::factory()->forGroup($group)->forModule('stub')->withCapacity(1)->create([
            'hostname' => 'full.example.test',
        ]);
        Service::factory()->create([
            'module' => 'stub',
            'node_id' => Node::query()->where('hostname', 'full.example.test')->value('id'),
            'status' => ServiceStatus::Active,
        ]);

        $eligible = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(5)->create([
            'hostname' => 'ok.example.test',
        ]);

        $product = Product::factory()
            ->withModule('stub')
            ->withProvisioningRules([
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create(['module' => 'stub']);

        $result = $this->selection->selectForService($service);

        $this->assertSame($eligible->id, $result->nodeId());
    }

    public function test_provisioning_engine_assigns_node_before_provider_create(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'prov-group']);
        $node = Node::factory()->forGroup($group)->forModule('stub')->create([
            'hostname' => 'node-01.example.test',
            'api_url' => 'https://panel.example.test',
        ]);

        $seen = (object) ['nodeId' => null, 'hostname' => null];

        $this->registry->registerServer(new class('stub', $seen) implements ServerProviderInterface
        {
            public function __construct(
                private readonly string $keyValue,
                private readonly object $seen,
            ) {
            }

            public function key(): string
            {
                return $this->keyValue;
            }

            public function label(): string
            {
                return 'Stub';
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                $this->seen->nodeId = $request->nodeId;
                $this->seen->hostname = $request->node?->hostname;

                return ProvisioningResponse::success(
                    externalId: 'ext-'.$request->serviceId,
                    nodeId: $request->nodeId,
                );
            }

            public function suspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function unsuspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function terminate(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function reinstall(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }
        });

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
        $this->assertSame($node->id, $seen->nodeId);
        $this->assertSame('node-01.example.test', $seen->hostname);
        $this->assertSame($node->id, $service->fresh()->node_id);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
    }

    public function test_keeps_existing_eligible_node_assignment(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'keep']);
        $node = Node::factory()->forGroup($group)->forModule('stub')->create();
        Node::factory()->forGroup($group)->forModule('stub')->create();

        $product = Product::factory()
            ->withModule('stub')
            ->withProvisioningRules([
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create([
            'module' => 'stub',
            'node_id' => $node->id,
        ]);

        $result = $this->selection->selectForService($service);

        $this->assertSame($node->id, $result->nodeId());
    }

    public function test_selects_node_assigned_via_group_relation_only(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'relation-only']);
        $node = Node::factory()->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'relation-only.example.test',
            'node_group_id' => null,
        ]);

        \Core\Nodes\Models\NodeGroupRelation::query()->create([
            'node_id' => $node->id,
            'node_group_id' => $group->id,
            'is_primary' => false,
            'sort_order' => 0,
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

        $result = $this->selection->selectForService($service);

        $this->assertSame($node->id, $result->nodeId());
    }
}
