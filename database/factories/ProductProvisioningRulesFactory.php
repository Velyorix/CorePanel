<?php

namespace Database\Factories;

use Core\Nodes\Models\NodeGroup;
use Core\Products\Models\Product;
use Core\Products\Models\ProductProvisioningRules;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductProvisioningRules>
 */
class ProductProvisioningRulesFactory extends Factory
{
    protected $model = ProductProvisioningRules::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'auto_provision' => false,
            'send_welcome_email' => true,
            'welcome_email_template' => null,
            'node_group_key' => null,
            'node_group_id' => null,
            'config' => null,
        ];
    }

    public function autoProvision(): static
    {
        return $this->state(fn (): array => [
            'auto_provision' => true,
        ]);
    }

    public function withoutWelcomeEmail(): static
    {
        return $this->state(fn (): array => [
            'send_welcome_email' => false,
        ]);
    }

    public function forNodeGroup(NodeGroup|string $group): static
    {
        if ($group instanceof NodeGroup) {
            return $this->state(fn (): array => [
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ]);
        }

        return $this->state(fn (): array => [
            'node_group_key' => $group,
        ]);
    }
}
