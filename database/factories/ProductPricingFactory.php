<?php

namespace Database\Factories;

use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Core\Products\Models\ProductPricing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPricing>
 */
class ProductPricingFactory extends Factory
{
    protected $model = ProductPricing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'billing_cycle' => BillingCycle::Monthly,
            'custom_interval_days' => null,
            'price' => fake()->randomFloat(2, 1, 500),
            'setup_fee' => fake()->randomFloat(2, 0, 50),
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'is_enabled' => false,
        ]);
    }

    public function forCycle(BillingCycle $cycle, ?int $customIntervalDays = null): static
    {
        return $this->state(fn (): array => [
            'billing_cycle' => $cycle,
            'custom_interval_days' => $cycle === BillingCycle::Custom
                ? ($customIntervalDays ?? 45)
                : null,
        ]);
    }
}
