<?php

namespace Database\Factories;

use Core\Nodes\Enums\NodeMetricStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NodeMetric>
 */
class NodeMetricFactory extends Factory
{
    protected $model = NodeMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'node_id' => Node::factory(),
            'current_services' => fake()->numberBetween(0, 20),
            'max_services' => 50,
            'cpu_usage' => fake()->randomFloat(2, 1, 90),
            'ram_usage' => fake()->randomFloat(2, 512, 32768),
            'disk_usage' => fake()->randomFloat(2, 10, 500),
            'network_in' => fake()->randomFloat(2, 1, 100),
            'network_out' => fake()->randomFloat(2, 1, 100),
            'load_average' => fake()->randomFloat(2, 0.1, 8),
            'capacity_available' => true,
            'status' => NodeMetricStatus::Success,
            'payload' => null,
            'collected_at' => now(),
            'created_at' => now(),
        ];
    }

    public function forNode(Node $node): static
    {
        return $this->state(fn (): array => [
            'node_id' => $node->id,
        ]);
    }
}
