<?php

namespace Tests\Feature\Nodes;

use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Models\NodeGroupRelation;
use Core\Nodes\Services\NodeAllocationAlgorithm;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeAllocationAlgorithmTest extends TestCase
{
    use RefreshDatabase;

    private NodeAllocationAlgorithm $algorithm;

    protected function setUp(): void
    {
        parent::setUp();

        config(['corepanel.nodes.allocation.require_credentials' => false]);

        $this->algorithm = app(NodeAllocationAlgorithm::class);
    }

    public function test_allocation_algorithm_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeAllocationAlgorithm::class),
            app(NodeAllocationAlgorithm::class),
        );
    }

    public function test_selects_node_with_lowest_resource_utilization(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'util-group']);

        $busy = Node::factory()->forGroup($group)->forModule('stub')->withCapacityLimits([
            'max_services' => 10,
            'max_cpu_cores' => 8,
        ])->create([
            'name' => 'Busy Node',
            'hostname' => 'busy.example.test',
            'config' => [
                'capacity' => [
                    'allocated' => [
                        'cpu_cores' => 6,
                    ],
                ],
            ],
        ]);

        $free = Node::factory()->forGroup($group)->forModule('stub')->withCapacityLimits([
            'max_services' => 10,
            'max_cpu_cores' => 8,
        ])->create([
            'name' => 'Free Node',
            'hostname' => 'free.example.test',
            'config' => [
                'capacity' => [
                    'allocated' => [
                        'cpu_cores' => 1,
                    ],
                ],
            ],
        ]);

        Service::factory()->count(2)->create([
            'module' => 'stub',
            'node_id' => $busy->id,
            'status' => ServiceStatus::Active,
        ]);

        Service::factory()->count(2)->create([
            'module' => 'stub',
            'node_id' => $free->id,
            'status' => ServiceStatus::Active,
        ]);

        $selected = $this->algorithm->selectBest($group, 'stub');

        $this->assertNotNull($selected);
        $this->assertSame($free->id, $selected->id);
    }

    public function test_finds_node_assigned_via_group_relation_without_primary_group(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'pivot-group']);
        $node = Node::factory()->forModule('stub')->withCapacity(10)->create([
            'name' => 'Pivot Node',
            'hostname' => 'pivot.example.test',
            'node_group_id' => null,
        ]);

        NodeGroupRelation::query()->create([
            'node_id' => $node->id,
            'node_group_id' => $group->id,
            'is_primary' => false,
            'sort_order' => 0,
        ]);

        $selected = $this->algorithm->selectBest($group, 'stub');

        $this->assertNotNull($selected);
        $this->assertSame($node->id, $selected->id);
    }

    public function test_uses_pivot_sort_order_as_tiebreaker(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'sort-group']);

        $second = Node::factory()->forModule('stub')->withCapacity(10)->create([
            'name' => 'Second Priority',
            'hostname' => 'second.example.test',
            'node_group_id' => null,
            'sort_order' => 99,
        ]);

        $first = Node::factory()->forModule('stub')->withCapacity(10)->create([
            'name' => 'First Priority',
            'hostname' => 'first.example.test',
            'node_group_id' => null,
            'sort_order' => 99,
        ]);

        NodeGroupRelation::query()->create([
            'node_id' => $second->id,
            'node_group_id' => $group->id,
            'is_primary' => false,
            'sort_order' => 5,
        ]);

        NodeGroupRelation::query()->create([
            'node_id' => $first->id,
            'node_group_id' => $group->id,
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $selected = $this->algorithm->selectBest($group, 'stub');

        $this->assertNotNull($selected);
        $this->assertSame($first->id, $selected->id);
    }

    public function test_skips_nodes_without_credentials_when_required(): void
    {
        config(['corepanel.nodes.allocation.require_credentials' => true]);

        $group = NodeGroup::factory()->create(['key' => 'cred-group']);

        Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'no-creds.example.test',
            'credentials' => null,
        ]);

        $configured = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'with-creds.example.test',
            'credentials' => ['api_key' => 'secret'],
        ]);

        $selected = $this->algorithm->selectBest($group, 'stub');

        $this->assertNotNull($selected);
        $this->assertSame($configured->id, $selected->id);
    }

    public function test_returns_null_when_no_eligible_node_exists(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'empty-group']);

        Node::factory()->forGroup($group)->forModule('stub')->disabled()->create([
            'hostname' => 'disabled.example.test',
        ]);

        $this->assertNull($this->algorithm->selectBest($group, 'stub'));
    }

    public function test_skips_nodes_with_unhealthy_health_state(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'health-group']);

        Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'offline-health.example.test',
            'config' => [
                'health' => [
                    'state' => \Core\Nodes\Enums\NodeHealthState::Offline->value,
                ],
            ],
        ]);

        $healthy = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'online-health.example.test',
            'config' => [
                'health' => [
                    'state' => \Core\Nodes\Enums\NodeHealthState::Online->value,
                ],
            ],
        ]);

        $selected = $this->algorithm->selectBest($group, 'stub');

        $this->assertNotNull($selected);
        $this->assertSame($healthy->id, $selected->id);
    }
}
