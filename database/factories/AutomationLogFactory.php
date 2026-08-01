<?php

namespace Database\Factories;

use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\AutomationRule;
use Core\Automation\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutomationLog>
 */
class AutomationLogFactory extends Factory
{
    protected $model = AutomationLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => null,
            'automation_rule_id' => null,
            'trigger_event' => 'invoice.paid',
            'status' => AutomationLogStatus::Pending,
            'attempt' => 0,
            'payload' => ['invoice_id' => fake()->numberBetween(1, 100)],
            'result' => null,
            'error_message' => null,
            'idempotency_key' => (string) Str::uuid(),
            'next_retry_at' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function forWorkflow(?Workflow $workflow = null): static
    {
        return $this->state(fn (): array => [
            'workflow_id' => $workflow?->id ?? Workflow::factory(),
            'trigger_event' => $workflow?->trigger_event ?? 'invoice.paid',
        ]);
    }

    public function forRule(?AutomationRule $rule = null): static
    {
        return $this->state(fn (): array => [
            'automation_rule_id' => $rule?->id ?? AutomationRule::factory(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => AutomationLogStatus::Succeeded,
            'attempt' => 1,
            'started_at' => now()->subSeconds(5),
            'finished_at' => now(),
            'result' => ['ok' => true],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => AutomationLogStatus::Failed,
            'attempt' => 1,
            'error_message' => 'Automation step failed.',
            'started_at' => now()->subSeconds(5),
            'finished_at' => now(),
        ]);
    }
}
