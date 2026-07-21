<?php

namespace Database\Factories;

use Core\Nodes\Enums\NodeClusterStatus;
use Core\Nodes\Models\NodeCluster;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NodeCluster>
 */
class NodeClusterFactory extends Factory
{
    protected $model = NodeCluster::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'key' => Str::slug($name, '_').'_'.fake()->unique()->numerify('##'),
            'location' => fake()->optional()->city(),
            'description' => fake()->optional()->sentence(),
            'status' => NodeClusterStatus::Active,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'status' => NodeClusterStatus::Disabled,
        ]);
    }
}
