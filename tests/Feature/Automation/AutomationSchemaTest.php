<?php

namespace Tests\Feature\Automation;

use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\AutomationRule;
use Core\Automation\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutomationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_automation_tables_exist(): void
    {
        foreach (['workflows', 'automation_rules', 'automation_logs'] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_workflow_factory_persists_trigger_conditions_and_steps(): void
    {
        $workflow = Workflow::factory()->forEvent('service.created')->create([
            'name' => 'Provision on create',
            'conditions' => [
                'all' => [
                    ['field' => 'service.status', 'operator' => 'eq', 'value' => 'pending'],
                ],
            ],
            'steps' => [
                ['type' => 'provision'],
                ['type' => 'notify', 'channel' => 'mail'],
            ],
            'priority' => 10,
        ]);

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'name' => 'Provision on create',
            'trigger_event' => 'service.created',
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->assertSame('provision', $workflow->steps[0]['type'] ?? null);
        $this->assertNotEmpty($workflow->slug);

        $this->assertTrue(
            Workflow::query()->active()->forEvent('service.created')->whereKey($workflow->id)->exists(),
        );
    }

    public function test_automation_rule_factory_persists_condition_and_action_json(): void
    {
        $rule = AutomationRule::factory()->create([
            'name' => 'Suspend overdue',
            'condition_json' => [
                'all' => [
                    ['field' => 'invoice.status', 'operator' => 'eq', 'value' => 'overdue'],
                    ['field' => 'days_overdue', 'operator' => 'gt', 'value' => 3],
                ],
            ],
            'action_json' => ['type' => 'suspend_service'],
            'priority' => 50,
        ]);

        $this->assertDatabaseHas('automation_rules', [
            'id' => $rule->id,
            'name' => 'Suspend overdue',
            'priority' => 50,
            'is_active' => true,
        ]);

        $this->assertSame('suspend_service', $rule->action_json['type'] ?? null);
        $this->assertCount(2, $rule->condition_json['all'] ?? []);
    }

    public function test_automation_log_links_to_workflow_and_rule(): void
    {
        $workflow = Workflow::factory()->create(['trigger_event' => 'invoice.paid']);
        $rule = AutomationRule::factory()->create();

        $log = AutomationLog::factory()
            ->forWorkflow($workflow)
            ->forRule($rule)
            ->succeeded()
            ->create([
                'idempotency_key' => 'invoice.paid:42:workflow:'.$workflow->id,
            ]);

        $this->assertSame(AutomationLogStatus::Succeeded, $log->status);
        $this->assertSame($workflow->id, $log->workflow_id);
        $this->assertSame($rule->id, $log->automation_rule_id);
        $this->assertSame('invoice.paid', $log->trigger_event);
        $this->assertTrue($log->workflow()->is($workflow));
        $this->assertTrue($log->automationRule()->is($rule));

        $this->assertDatabaseHas('automation_logs', [
            'id' => $log->id,
            'status' => AutomationLogStatus::Succeeded->value,
            'idempotency_key' => 'invoice.paid:42:workflow:'.$workflow->id,
        ]);
    }

    public function test_idempotency_key_is_unique(): void
    {
        AutomationLog::factory()->create([
            'idempotency_key' => 'unique-key-1',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AutomationLog::factory()->create([
            'idempotency_key' => 'unique-key-1',
        ]);
    }
}
