<?php

namespace Database\Factories;

use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
            'status' => ProductCategoryStatus::Active,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductCategoryStatus::Hidden,
        ]);
    }

    public function childOf(ProductCategory $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->id,
        ]);
    }
}
