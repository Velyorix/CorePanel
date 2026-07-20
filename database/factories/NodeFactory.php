<?php

namespace Database\Factories;

use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Node>
 */
class NodeFactory extends Factory
{
    protected $model = Node::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'type' => NodeType::General,
            'module' => null,
            'hostname' => fake()->unique()->domainName(),
            'ip_address' => fake()->ipv4(),
            'api_url' => 'https://'.fake()->domainName(),
            'status' => NodeStatus::Active,
            'max_services' => 50,
            'sort_order' => 0,
            'node_group_id' => null,
            'credentials' => null,
            'config' => null,
        ];
    }

    public function forGroup(NodeGroup $group): static
    {
        return $this->state(fn (): array => [
            'node_group_id' => $group->id,
        ]);
    }

    public function forModule(string $module): static
    {
        return $this->state(fn (): array => [
            'module' => $module,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => NodeStatus::Active,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'status' => NodeStatus::Disabled,
        ]);
    }

    public function withCapacity(int $maxServices): static
    {
        return $this->state(fn (): array => [
            'max_services' => $maxServices,
        ]);
    }
}
