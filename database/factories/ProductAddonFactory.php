<?php

namespace Database\Factories;

use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAddon>
 */
class ProductAddonFactory extends Factory
{
    protected $model = ProductAddon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'product_id' => Product::factory(),
            'key' => Str::slug($name, '_').'_'.fake()->unique()->numerify('##'),
            'name' => ucfirst($name),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 1, 50),
            'setup_fee' => fake()->randomFloat(2, 0, 20),
            'billing_cycle' => BillingCycle::Monthly,
            'is_enabled' => true,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'is_enabled' => false,
        ]);
    }

    public function forCycle(BillingCycle $cycle): static
    {
        return $this->state(fn (): array => [
            'billing_cycle' => $cycle,
        ]);
    }
}
