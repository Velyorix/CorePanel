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

    /**
     * @param  array{
     *     max_services?: int|null,
     *     max_cpu_cores?: int|null,
     *     max_ram_mb?: int|null,
     *     max_disk_gb?: int|null,
     *     max_bandwidth_mbps?: int|null
     * }  $limits
     */
    public function withCapacityLimits(array $limits): static
    {
        return $this->state(fn (): array => array_filter([
            'max_services' => $limits['max_services'] ?? null,
            'max_cpu_cores' => $limits['max_cpu_cores'] ?? null,
            'max_ram_mb' => $limits['max_ram_mb'] ?? null,
            'max_disk_gb' => $limits['max_disk_gb'] ?? null,
            'max_bandwidth_mbps' => $limits['max_bandwidth_mbps'] ?? null,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
