<?php

namespace Database\Factories;

use Core\KnowledgeBase\Enums\KbCategoryStatus;
use Core\KnowledgeBase\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KbCategory>
 */
class KbCategoryFactory extends Factory
{
    protected $model = KbCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
            'status' => KbCategoryStatus::Active,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => [
            'status' => KbCategoryStatus::Hidden,
        ]);
    }
}
