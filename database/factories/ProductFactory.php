<?php

namespace Database\Factories;

use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Models\ProductPricing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => ProductCategory::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->paragraph(),
            'type' => ProductType::Other,
            'module' => null,
            'status' => ProductStatus::Draft,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function ofType(ProductType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Published,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Archived,
        ]);
    }

    public function withoutCategory(): static
    {
        return $this->state(fn (): array => [
            'category_id' => null,
        ]);
    }

    /**
     * @param  list<BillingCycle>|null  $cycles
     */
    public function withPricing(?array $cycles = null, string $price = '9.99', string $setupFee = '0.00'): static
    {
        $cycles ??= [BillingCycle::Monthly];

        return $this->afterCreating(function (Product $product) use ($cycles, $price, $setupFee): void {
            foreach ($cycles as $cycle) {
                ProductPricing::factory()->create([
                    'product_id' => $product->id,
                    'billing_cycle' => $cycle,
                    'price' => $price,
                    'setup_fee' => $setupFee,
                    'is_enabled' => true,
                ]);
            }
        });
    }
}
