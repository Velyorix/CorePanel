<?php

namespace Database\Factories;

use Core\Tickets\Enums\TicketCategoryStatus;
use Core\Tickets\Models\TicketCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketCategory>
 */
class TicketCategoryFactory extends Factory
{
    protected $model = TicketCategory::class;

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
            'status' => TicketCategoryStatus::Active,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketCategoryStatus::Hidden,
        ]);
    }
}
