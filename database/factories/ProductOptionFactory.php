<?php

namespace Database\Factories;

use Core\Products\Enums\ProductOptionType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductOption>
 */
class ProductOptionFactory extends Factory
{
    protected $model = ProductOption::class;

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
            'type' => ProductOptionType::Text,
            'required' => false,
            'sort_order' => fake()->numberBetween(0, 50),
            'config' => null,
        ];
    }

    public function required(): static
    {
        return $this->state(fn (): array => [
            'required' => true,
        ]);
    }

    public function select(array $choices = []): static
    {
        if ($choices === []) {
            $choices = [
                ['value' => 'small', 'label' => 'Small', 'price_delta' => 0],
                ['value' => 'large', 'label' => 'Large', 'price_delta' => 5],
            ];
        }

        return $this->state(fn (): array => [
            'type' => ProductOptionType::Select,
            'config' => ['choices' => $choices],
        ]);
    }

    public function quantity(int $min = 1, int $max = 10): static
    {
        return $this->state(fn (): array => [
            'type' => ProductOptionType::Quantity,
            'config' => [
                'min' => $min,
                'max' => $max,
                'step' => 1,
            ],
        ]);
    }
}
