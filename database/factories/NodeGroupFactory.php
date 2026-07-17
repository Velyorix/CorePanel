<?php

namespace Database\Factories;

use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Core\Nodes\Models\NodeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NodeGroup>
 */
class NodeGroupFactory extends Factory
{
    protected $model = NodeGroup::class;

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
            'type' => NodeGroupType::General,
            'description' => fake()->optional()->sentence(),
            'status' => NodeGroupStatus::Active,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }

    public function ofType(NodeGroupType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'status' => NodeGroupStatus::Disabled,
        ]);
    }
}
