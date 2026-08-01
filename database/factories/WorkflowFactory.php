<?php

namespace Database\Factories;

use Core\Automation\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'trigger_event' => 'invoice.paid',
            'conditions' => null,
            'steps' => [
                ['type' => 'create_service'],
                ['type' => 'send_email', 'template' => 'service.created'],
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
        return $this->state(fn (): array => [
            'trigger_event' => $event,
        ]);
    }
}
