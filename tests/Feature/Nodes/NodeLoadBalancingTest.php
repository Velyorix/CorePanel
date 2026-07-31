<?php

namespace Tests\Feature\Nodes;

use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Models\NodeHealthCheck;
use Core\Nodes\Services\NodeAllocationAlgorithm;
use Core\Nodes\Services\NodeLoadBalancingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NodeLoadBalancingTest extends TestCase
{
    use RefreshDatabase;

    private NodeAllocationAlgorithm $algorithm;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.nodes.allocation.require_credentials' => false,
            'corepanel.nodes.allocation.load_balancing.enabled' => true,
            'corepanel.nodes.allocation.load_balancing.utilization_band' => 0.15,
            'corepanel.nodes.allocation.load_balancing.use_reliability_history' => false,
        ]);

        Cache::flush();

        $this->algorithm = app(NodeAllocationAlgorithm::class);
    }

    public function test_load_balancing_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeLoadBalancingService::class),
            app(NodeLoadBalancingService::class),
        );
    }

    public function test_weighted_round_robin_distributes_by_node_weight(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'weighted-group']);

        $light = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(100)->create([
            'name' => 'Light Node',
            'hostname' => 'light.example.test',
            'config' => [
                'allocation' => ['weight' => 100],
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        $heavy = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(100)->create([
            'name' => 'Heavy Node',
            'hostname' => 'heavy.example.test',
            'config' => [
                'allocation' => ['weight' => 300],
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        $counts = [
            $light->id => 0,
            $heavy->id => 0,
        ];

        for ($iteration = 0; $iteration < 8; $iteration++) {
            $selected = $this->algorithm->selectBest($group, 'stub');
            $this->assertNotNull($selected);
            $counts[$selected->id]++;
        }

        $this->assertGreaterThan($counts[$light->id], $counts[$heavy->id]);
    }

    public function test_excludes_nodes_outside_utilization_band(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'band-group']);

        $free = Node::factory()->forGroup($group)->forModule('stub')->withCapacityLimits([
            'max_services' => 10,
            'max_cpu_cores' => 10,
        ])->create([
            'hostname' => 'free.example.test',
            'config' => [
                'allocation' => ['weight' => 100],
                'health' => ['state' => NodeHealthState::Online->value],
                'capacity' => ['allocated' => ['cpu_cores' => 1]],
            ],
        ]);

        $busy = Node::factory()->forGroup($group)->forModule('stub')->withCapacityLimits([
            'max_services' => 10,
            'max_cpu_cores' => 10,
        ])->create([
            'hostname' => 'busy.example.test',
            'config' => [
                'allocation' => ['weight' => 500],
                'health' => ['state' => NodeHealthState::Online->value],
                'capacity' => ['allocated' => ['cpu_cores' => 9]],
            ],
        ]);

        for ($iteration = 0; $iteration < 5; $iteration++) {
            $selected = $this->algorithm->selectBest($group, 'stub');
            $this->assertNotNull($selected);
            $this->assertSame($free->id, $selected->id);
        }

        $this->assertGreaterThan(
            $this->algorithm->utilizationScore($free),
            $this->algorithm->utilizationScore($busy),
        );
    }

    public function test_degraded_nodes_receive_lower_selection_priority(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'health-weight-group']);

        $online = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(100)->create([
            'hostname' => 'online.example.test',
            'config' => [
                'allocation' => ['weight' => 100],
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        $degraded = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(100)->create([
            'hostname' => 'degraded.example.test',
            'config' => [
                'allocation' => ['weight' => 100],
                'health' => ['state' => NodeHealthState::Degraded->value],
            ],
        ]);

        $counts = [
            $online->id => 0,
            $degraded->id => 0,
        ];

        for ($iteration = 0; $iteration < 10; $iteration++) {
            $selected = $this->algorithm->selectBest($group, 'stub');
            $this->assertNotNull($selected);
            $counts[$selected->id]++;
        }

        $this->assertGreaterThan($counts[$degraded->id], $counts[$online->id]);
    }

    public function test_reliability_history_reduces_weight_for_unstable_nodes(): void
    {
        config(['corepanel.nodes.allocation.load_balancing.use_reliability_history' => true]);

        $group = NodeGroup::factory()->create(['key' => 'reliability-group']);

        $stable = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(100)->create([
            'hostname' => 'stable.example.test',
            'config' => [
                'allocation' => ['weight' => 100],
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        $unstable = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(100)->create([
            'hostname' => 'unstable.example.test',
            'config' => [
                'allocation' => ['weight' => 100],
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        foreach (range(1, 8) as $index) {
            NodeHealthCheck::query()->create([
                'node_id' => $stable->id,
                'state' => NodeHealthState::Online,
                'checked_at' => now()->subHours($index),
                'created_at' => now(),
            ]);
        }

        foreach (range(1, 8) as $index) {
            NodeHealthCheck::query()->create([
                'node_id' => $unstable->id,
                'state' => $index % 2 === 0 ? NodeHealthState::Online : NodeHealthState::Offline,
                'checked_at' => now()->subHours($index),
                'created_at' => now(),
            ]);
        }

        $counts = [
            $stable->id => 0,
            $unstable->id => 0,
        ];

        for ($iteration = 0; $iteration < 12; $iteration++) {
            $selected = $this->algorithm->selectBest($group, 'stub');
            $this->assertNotNull($selected);
            $counts[$selected->id]++;
        }

        $this->assertGreaterThan($counts[$unstable->id], $counts[$stable->id]);
    }

    public function test_disabled_load_balancing_falls_back_to_lowest_utilization(): void
    {
        config(['corepanel.nodes.allocation.load_balancing.enabled' => false]);

        $group = NodeGroup::factory()->create(['key' => 'legacy-group']);

        Node::factory()->forGroup($group)->forModule('stub')->withCapacityLimits([
            'max_services' => 10,
            'max_cpu_cores' => 10,
        ])->create([
            'hostname' => 'busy.example.test',
            'config' => [
                'allocation' => ['weight' => 500],
                'capacity' => ['allocated' => ['cpu_cores' => 8]],
            ],
        ]);

        $free = Node::factory()->forGroup($group)->forModule('stub')->withCapacityLimits([
            'max_services' => 10,
            'max_cpu_cores' => 10,
        ])->create([
            'hostname' => 'free.example.test',
            'config' => [
                'allocation' => ['weight' => 100],
                'capacity' => ['allocated' => ['cpu_cores' => 1]],
            ],
        ]);

        for ($iteration = 0; $iteration < 3; $iteration++) {
            $selected = $this->algorithm->selectBest($group, 'stub');
            $this->assertSame($free->id, $selected?->id);
        }
    }
}
