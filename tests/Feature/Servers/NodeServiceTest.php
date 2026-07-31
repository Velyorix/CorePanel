<?php

namespace Tests\Feature\Servers;

use Core\Nodes\DataTransferObjects\NodeData;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Models\NodeGroupRelation;
use Core\Nodes\Services\NodeService;
use Core\Providers\Stubs\StubServerProvider;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Services\ProviderRegistry;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private NodeService $nodeService;

    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();
        $this->registry->registerServer(app(StubServerProvider::class));
        $this->registry->registerNode(app(StubNodeProvider::class));

        $this->nodeService = app(NodeService::class);
    }

    public function test_node_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeService::class),
            app(NodeService::class),
        );
    }

    #[DataProvider('nodeTypeProvider')]
    public function test_can_create_node_for_each_type(NodeType $type): void
    {
        $node = $this->nodeService->create(NodeData::fromArray([
            'name' => 'Server '.$type->value,
            'hostname' => $type->value.'.example.test',
            'type' => $type->value,
            'module' => 'stub',
            'ip_address' => '203.0.113.10',
            'api_url' => 'https://api.example.test',
            'max_services' => 25,
        ]));

        $this->assertSame($type, $node->type);
        $this->assertSame(NodeStatus::Active, $node->status);
        $this->assertSame('stub', $node->module);
        $this->assertSame(25, $node->max_services);
    }

    /**
     * @return array<string, array{0: NodeType}>
     */
    public static function nodeTypeProvider(): array
    {
        return [
            'general' => [NodeType::General],
            'game' => [NodeType::Game],
            'vps' => [NodeType::Vps],
            'web' => [NodeType::Web],
            'dedicated' => [NodeType::Dedicated],
            'custom' => [NodeType::Custom],
        ];
    }

    public function test_create_syncs_primary_group_and_relations(): void
    {
        $primary = NodeGroup::factory()->create(['key' => 'eu-west']);
        $secondary = NodeGroup::factory()->create(['key' => 'overflow']);

        $node = $this->nodeService->create(NodeData::fromArray([
            'name' => 'EU Node',
            'hostname' => 'eu-node.example.test',
            'type' => NodeType::Vps->value,
            'module' => 'stub',
            'node_group_id' => $primary->id,
            'group_ids' => [$primary->id, $secondary->id],
            'credentials' => ['api_key' => 'secret'],
            'config' => ['region' => 'eu'],
        ]));

        $this->assertSame($primary->id, $node->node_group_id);
        $this->assertCount(2, $node->groups);
        $this->assertSame(2, NodeGroupRelation::query()->where('node_id', $node->id)->count());
        $this->assertTrue((bool) NodeGroupRelation::query()
            ->where('node_id', $node->id)
            ->where('node_group_id', $primary->id)
            ->value('is_primary'));
        $this->assertSame(['api_key' => 'secret'], $node->credentials);
    }

    public function test_update_changes_node_fields_and_group_assignments(): void
    {
        $oldGroup = NodeGroup::factory()->create(['key' => 'old-group']);
        $newGroup = NodeGroup::factory()->create(['key' => 'new-group']);

        $node = $this->nodeService->create(NodeData::fromArray([
            'name' => 'Old Name',
            'hostname' => 'old.example.test',
            'type' => NodeType::Game->value,
            'module' => 'stub',
            'node_group_id' => $oldGroup->id,
        ]));

        $updated = $this->nodeService->update($node, NodeData::fromArray([
            'name' => 'New Name',
            'hostname' => 'new.example.test',
            'type' => NodeType::Web->value,
            'module' => 'stub',
            'status' => NodeStatus::Maintenance->value,
            'node_group_id' => $newGroup->id,
            'group_ids' => [$newGroup->id],
        ]));

        $this->assertSame('New Name', $updated->name);
        $this->assertSame(NodeType::Web, $updated->type);
        $this->assertSame(NodeStatus::Maintenance, $updated->status);
        $this->assertSame($newGroup->id, $updated->node_group_id);
        $this->assertSame(1, NodeGroupRelation::query()->where('node_id', $node->id)->count());
    }

    public function test_delete_removes_node_and_relations(): void
    {
        $group = NodeGroup::factory()->create();
        $node = $this->nodeService->create(NodeData::fromArray([
            'name' => 'Disposable',
            'hostname' => 'delete-me.example.test',
            'node_group_id' => $group->id,
            'group_ids' => [$group->id],
        ]));

        $this->nodeService->delete($node);

        $this->assertSoftDeleted('nodes', ['id' => $node->id]);
        $this->assertSame(0, NodeGroupRelation::query()->where('node_id', $node->id)->count());
    }

    public function test_cannot_delete_node_with_allocated_services(): void
    {
        $node = Node::factory()->create();

        Service::factory()->create([
            'node_id' => $node->id,
            'status' => ServiceStatus::Active,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('allocated services');

        $this->nodeService->delete($node);
    }

    public function test_rejects_unknown_module_on_create(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown provider module');

        $this->nodeService->create(NodeData::fromArray([
            'name' => 'Bad Module',
            'hostname' => 'bad.example.test',
            'module' => 'missing-provider',
        ]));
    }

    public function test_rejects_invalid_node_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid node type');

        NodeData::fromArray([
            'name' => 'Invalid',
            'hostname' => 'invalid.example.test',
            'type' => 'bare-metal',
        ]);
    }

    public function test_paginate_for_admin_filters_by_type_status_module_and_search(): void
    {
        $group = NodeGroup::factory()->create();

        $match = $this->nodeService->create(NodeData::fromArray([
            'name' => 'Alpha VPS',
            'hostname' => 'alpha-vps.example.test',
            'type' => NodeType::Vps->value,
            'module' => 'stub',
            'status' => NodeStatus::Active->value,
            'node_group_id' => $group->id,
        ]));

        $this->nodeService->create(NodeData::fromArray([
            'name' => 'Beta Game',
            'hostname' => 'beta-game.example.test',
            'type' => NodeType::Game->value,
            'module' => 'stub',
            'status' => NodeStatus::Disabled->value,
        ]));

        $results = $this->nodeService->paginateForAdmin([
            'q' => 'alpha',
            'type' => NodeType::Vps,
            'status' => NodeStatus::Active,
            'module' => 'stub',
            'node_group_id' => $group->id,
        ]);

        $this->assertSame(1, $results->total());
        $this->assertTrue($results->first()->is($match));
    }

    public function test_find_loads_group_relations_and_allocated_count(): void
    {
        $group = NodeGroup::factory()->create();
        $node = $this->nodeService->create(NodeData::fromArray([
            'name' => 'Lookup',
            'hostname' => 'lookup.example.test',
            'node_group_id' => $group->id,
            'group_ids' => [$group->id],
        ]));

        Service::factory()->create([
            'node_id' => $node->id,
            'status' => ServiceStatus::Active,
        ]);

        $found = $this->nodeService->find($node->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('group'));
        $this->assertTrue($found->relationLoaded('groups'));
        $this->assertSame(1, $found->allocated_services_count);
    }
}
