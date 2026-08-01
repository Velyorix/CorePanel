<?php

namespace Database\Factories;

use Core\Automation\Models\AutomationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'condition_json' => [
                'all' => [
                    ['field' => 'invoice.status', 'operator' => 'eq', 'value' => 'overdue'],
                    ['field' => 'days_overdue', 'operator' => 'gt', 'value' => 3],
                ],
            ],
            'action_json' => [
                'type' => 'suspend_service',
            ],
            'priority' => 100,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function forEvent(string $event): static
    {
        return $this->state(function (array $attributes) use ($event): array {
            $conditions = is_array($attributes['condition_json'] ?? null)
                ? $attributes['condition_json']
                : [];

            return [
                'condition_json' => [
                    'event' => $event,
                    ...$conditions,
                ],
            ];
        });
    }
}
