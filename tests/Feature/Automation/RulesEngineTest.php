<?php

namespace Tests\Feature\Automation;

use Core\Automation\DataTransferObjects\AutomationEventContext;
use Core\Automation\DataTransferObjects\RuleRunContext;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\AutomationRule;
use Core\Automation\Services\AutomationEventBus;
use Core\Automation\Services\RuleActionRegistry;
use Core\Automation\Services\RulesEngine;
use Core\Automation\Support\AutomationEvent;
use Core\Billing\Events\InvoiceOverdue;
use Core\Billing\Models\Invoice;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RulesEngineTest extends TestCase
{
    use RefreshDatabase;

    private RulesEngine $engine;

    private AutomationEventBus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.automation.enabled' => true,
            'corepanel.api.webhooks.enabled' => false,
        ]);

        $this->bus = app(AutomationEventBus::class);
        $this->bus->flush();
        $this->engine = app(RulesEngine::class);
        $this->engine->register();
    }

    public function test_engine_and_registry_are_singletons(): void
    {
        $this->assertSame(app(RulesEngine::class), app(RulesEngine::class));
        $this->assertSame(app(RuleActionRegistry::class), app(RuleActionRegistry::class));
    }

    public function test_matching_rule_runs_then_action_and_logs_success(): void
    {
        AutomationRule::factory()->create([
            'name' => 'Flag overdue',
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [
                    ['field' => 'days_overdue', 'operator' => 'gt', 'value' => 3],
                ],
            ],
            'action_json' => [
                'type' => 'set',
                'key' => 'should_suspend',
                'value' => true,
            ],
            'priority' => 10,
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::INVOICE_OVERDUE,
            [
                'invoice_id' => 5,
                'days_overdue' => 4,
            ],
        ));

        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame(AutomationLogStatus::Succeeded, $log->status);
        $this->assertTrue($log->result['bag']['should_suspend'] ?? false);
        $this->assertSame('set', $log->result['action']['type'] ?? null);
    }

    public function test_non_matching_conditions_skip_rule(): void
    {
        AutomationRule::factory()->create([
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [
                    ['field' => 'days_overdue', 'operator' => 'gt', 'value' => 3],
                ],
            ],
            'action_json' => ['type' => 'log', 'message' => 'nope'],
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::INVOICE_OVERDUE,
            ['days_overdue' => 2],
        ));

        $this->assertCount(0, $logs);
        $this->assertSame(0, AutomationLog::query()->count());
    }

    public function test_event_scoped_rule_ignores_other_events(): void
    {
        AutomationRule::factory()->create([
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [
                    ['field' => 'days_overdue', 'operator' => 'gt', 'value' => 0],
                ],
            ],
            'action_json' => ['type' => 'noop'],
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::INVOICE_PAID,
            ['days_overdue' => 10],
        ));

        $this->assertCount(0, $logs);
    }

    public function test_suspend_service_action_suspends_target_service(): void
    {
        $service = Service::factory()->active()->create();

        AutomationRule::factory()->create([
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [
                    ['field' => 'days_overdue', 'operator' => 'gte', 'value' => 3],
                ],
            ],
            'action_json' => [
                'type' => 'suspend_service',
            ],
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::INVOICE_OVERDUE,
            [
                'days_overdue' => 3,
                'service_id' => $service->id,
            ],
        ));

        $this->assertSame(AutomationLogStatus::Succeeded, $logs->first()->status);
        $this->assertSame(ServiceStatus::Suspended, $service->fresh()?->status);
        $this->assertSame(ServiceStatus::Suspended->value, $logs->first()->result['action']['result']['status'] ?? null);
    }

    public function test_evaluate_without_event_runs_unscoped_rules(): void
    {
        AutomationRule::factory()->create([
            'condition_json' => [
                'all' => [
                    ['field' => 'invoice.status', 'operator' => 'eq', 'value' => 'overdue'],
                    ['field' => 'days_overdue', 'operator' => 'gt', 'value' => 3],
                ],
            ],
            'action_json' => [
                'type' => 'log',
                'message' => 'scheduled-suspend',
            ],
        ]);

        AutomationRule::factory()->create([
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [
                    ['field' => 'days_overdue', 'operator' => 'gt', 'value' => 0],
                ],
            ],
            'action_json' => ['type' => 'noop'],
        ]);

        $logs = $this->engine->evaluate([
            'invoice' => ['status' => 'overdue'],
            'days_overdue' => 5,
        ]);

        $this->assertCount(1, $logs);
        $this->assertSame(['scheduled-suspend'], $logs->first()->result['bag']['_logs'] ?? null);
        $this->assertSame('rule.evaluate', $logs->first()->trigger_event);
    }

    public function test_custom_rule_action_can_be_registered_dynamically(): void
    {
        app(RuleActionRegistry::class)->register(
            'mark_reviewed',
            function (array $action, RuleRunContext $context): array {
                $context->set('reviewed', true);

                return ['reviewed' => true];
            },
        );

        AutomationRule::factory()->create([
            'condition_json' => [
                'any' => [
                    ['field' => 'priority', 'operator' => 'eq', 'value' => 'high'],
                ],
            ],
            'action_json' => ['type' => 'mark_reviewed'],
        ]);

        $logs = $this->engine->evaluate(['priority' => 'high']);

        $this->assertSame(AutomationLogStatus::Succeeded, $logs->first()->status);
        $this->assertTrue($logs->first()->result['bag']['reviewed'] ?? false);
    }

    public function test_domain_event_triggers_scoped_rule_through_bus(): void
    {
        AutomationRule::factory()->create([
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [
                    ['field' => 'amount', 'operator' => 'gte', 'value' => 1],
                ],
            ],
            'action_json' => [
                'type' => 'log',
                'message' => 'overdue-bridged',
            ],
        ]);

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '11.00',
            'subtotal' => '11.00',
        ]);

        event(new InvoiceOverdue($invoice->fresh() ?? $invoice));

        $this->assertDatabaseHas('automation_logs', [
            'trigger_event' => AutomationEvent::INVOICE_OVERDUE,
            'status' => AutomationLogStatus::Succeeded->value,
        ]);

        $log = AutomationLog::query()
            ->whereNotNull('automation_rule_id')
            ->latest('id')
            ->first();

        $this->assertSame(['overdue-bridged'], $log?->result['bag']['_logs'] ?? null);
    }

    public function test_rules_run_in_priority_order(): void
    {
        $seen = [];

        app(RuleActionRegistry::class)->register(
            'capture',
            function (array $action, RuleRunContext $context) use (&$seen): array {
                $seen[] = (string) ($action['label'] ?? '');

                return [];
            },
        );

        AutomationRule::factory()->create([
            'priority' => 20,
            'condition_json' => ['all' => [['field' => 'ok', 'operator' => 'eq', 'value' => true]]],
            'action_json' => ['type' => 'capture', 'label' => 'second'],
        ]);
        AutomationRule::factory()->create([
            'priority' => 5,
            'condition_json' => ['all' => [['field' => 'ok', 'operator' => 'eq', 'value' => true]]],
            'action_json' => ['type' => 'capture', 'label' => 'first'],
        ]);

        $this->engine->evaluate(['ok' => true]);

        $this->assertSame(['first', 'second'], $seen);
    }

    public function test_failed_action_marks_log_failed(): void
    {
        AutomationRule::factory()->create([
            'condition_json' => [],
            'action_json' => ['type' => 'does_not_exist'],
        ]);

        $logs = $this->engine->evaluate(['x' => 1]);

        $this->assertSame(AutomationLogStatus::Failed, $logs->first()->status);
        $this->assertStringContainsString('does_not_exist', (string) $logs->first()->error_message);
    }
}
