<?php

namespace Tests\Feature\Automation;

use Core\Automation\DataTransferObjects\AutomationEventContext;
use Core\Automation\DataTransferObjects\WorkflowRunContext;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\Workflow;
use Core\Automation\Services\AutomationEventBus;
use Core\Automation\Services\WorkflowActionRegistry;
use Core\Automation\Services\WorkflowEngine;
use Core\Automation\Support\AutomationEvent;
use Core\Billing\Events\InvoicePaid;
use Core\Billing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowEngine $engine;

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
        $this->engine = app(WorkflowEngine::class);
        $this->engine->register();
    }

    public function test_engine_and_registry_are_singletons(): void
    {
        $this->assertSame(app(WorkflowEngine::class), app(WorkflowEngine::class));
        $this->assertSame(app(WorkflowActionRegistry::class), app(WorkflowActionRegistry::class));
    }

    public function test_matching_workflow_runs_steps_in_order_and_logs_success(): void
    {
        $workflow = Workflow::factory()->create([
            'trigger_event' => AutomationEvent::INVOICE_PAID,
            'conditions' => [
                'all' => [
                    ['field' => 'amount', 'operator' => 'gte', 'value' => 10],
                ],
            ],
            'steps' => [
                ['type' => 'set', 'key' => 'flag', 'value' => 'ready'],
                ['type' => 'log', 'message' => 'invoice-paid-handled'],
                ['type' => 'noop'],
            ],
            'priority' => 10,
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::INVOICE_PAID,
            ['invoice_id' => 7, 'amount' => 15],
        ));

        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame(AutomationLogStatus::Succeeded, $log->status);
        $this->assertSame($workflow->id, $log->workflow_id);
        $this->assertSame(['set', 'log', 'noop'], array_column($log->result['steps'], 'type'));
        $this->assertSame('ready', $log->result['bag']['flag'] ?? null);
        $this->assertSame(['invoice-paid-handled'], $log->result['bag']['_logs'] ?? null);
    }

    public function test_non_matching_conditions_skip_workflow(): void
    {
        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::INVOICE_PAID,
            'conditions' => [
                'all' => [
                    ['field' => 'amount', 'operator' => 'gt', 'value' => 100],
                ],
            ],
            'steps' => [
                ['type' => 'log', 'message' => 'should-not-run'],
            ],
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::INVOICE_PAID,
            ['amount' => 5],
        ));

        $this->assertCount(0, $logs);
        $this->assertSame(0, AutomationLog::query()->count());
    }

    public function test_inactive_workflow_is_ignored(): void
    {
        Workflow::factory()->inactive()->create([
            'trigger_event' => AutomationEvent::SERVICE_CREATED,
            'steps' => [['type' => 'noop']],
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::SERVICE_CREATED,
            ['service_id' => 1],
        ));

        $this->assertCount(0, $logs);
    }

    public function test_unknown_action_schedules_retry_by_default(): void
    {
        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::TICKET_CREATED,
            'steps' => [
                ['type' => 'noop'],
                ['type' => 'does_not_exist'],
            ],
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::TICKET_CREATED,
            ['ticket_id' => 3],
        ));

        $this->assertCount(1, $logs);
        $this->assertSame(AutomationLogStatus::Retrying, $logs->first()->status);
        $this->assertStringContainsString('does_not_exist', (string) $logs->first()->error_message);
        $this->assertNotNull($logs->first()->next_retry_at);
        $this->assertCount(1, $logs->first()->result['steps'] ?? []);
    }

    public function test_unknown_action_marks_failed_when_retries_exhausted(): void
    {
        config(['corepanel.automation.retry.max_attempts' => 1]);

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::TICKET_CREATED,
            'steps' => [
                ['type' => 'does_not_exist'],
            ],
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::TICKET_CREATED,
            ['ticket_id' => 3],
        ));

        $this->assertSame(AutomationLogStatus::Failed, $logs->first()->status);
        $this->assertNull($logs->first()->next_retry_at);
    }

    public function test_custom_action_can_be_registered_dynamically(): void
    {
        app(WorkflowActionRegistry::class)->register(
            'double_amount',
            function (array $step, WorkflowRunContext $context): array {
                $amount = (float) $context->get('amount', 0);
                $context->set('amount', $amount * 2);

                return ['amount' => $amount * 2];
            },
        );

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::INVOICE_OVERDUE,
            'steps' => [
                ['type' => 'double_amount'],
            ],
        ]);

        $logs = $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::INVOICE_OVERDUE,
            ['amount' => 12],
        ));

        $this->assertSame(AutomationLogStatus::Succeeded, $logs->first()->status);
        $this->assertEquals(24, $logs->first()->result['bag']['amount'] ?? null);
    }

    public function test_domain_event_triggers_workflow_through_event_bus(): void
    {
        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::INVOICE_PAID,
            'conditions' => null,
            'steps' => [
                ['type' => 'log', 'message' => 'bridged'],
            ],
        ]);

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '20.00',
            'subtotal' => '20.00',
        ]);

        event(new InvoicePaid($invoice->fresh() ?? $invoice));

        $this->assertDatabaseHas('automation_logs', [
            'trigger_event' => AutomationEvent::INVOICE_PAID,
            'status' => AutomationLogStatus::Succeeded->value,
        ]);

        $log = AutomationLog::query()->latest('id')->first();
        $this->assertSame($invoice->id, $log?->payload['data']['invoice_id'] ?? null);
        $this->assertSame(['bridged'], $log?->result['bag']['_logs'] ?? null);
    }

    public function test_workflows_run_in_priority_order(): void
    {
        $seen = [];

        app(WorkflowActionRegistry::class)->register(
            'capture',
            function (array $step, WorkflowRunContext $context) use (&$seen): array {
                $seen[] = (string) ($step['label'] ?? '');

                return [];
            },
        );

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::NODE_OFFLINE,
            'priority' => 20,
            'steps' => [['type' => 'capture', 'label' => 'second']],
        ]);
        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::NODE_OFFLINE,
            'priority' => 5,
            'steps' => [['type' => 'capture', 'label' => 'first']],
        ]);

        $this->engine->handle(AutomationEventContext::make(
            AutomationEvent::NODE_OFFLINE,
            ['node_id' => 1],
        ));

        $this->assertSame(['first', 'second'], $seen);
    }
}
