<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Jobs\CheckInvoicesDueJob;
use Core\Automation\Jobs\EvaluateScheduledRulesJob;
use Core\Automation\Jobs\RetryFailedSystemJobsJob;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\AutomationRule;
use Core\Automation\Models\Workflow;
use Core\Automation\Services\AutomationEventBus;
use Core\Automation\Services\AutomationRetryService;
use Core\Automation\Services\RuleActionRegistry;
use Core\Automation\Services\RulesEngine;
use Core\Automation\Services\WorkflowActionRegistry;
use Core\Automation\Services\WorkflowEngine;
use Core\Automation\Support\AutomationEvent;
use Core\Billing\Events\InvoiceOverdue;
use Core\Billing\Events\InvoicePaid;
use Core\Billing\Models\Invoice;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * End-to-end smoke for the automation engine: events, rules, retries, idempotency, admin.
 */
class AutomationEngineAcceptanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.automation.enabled' => true,
            'corepanel.api.webhooks.enabled' => false,
            'corepanel.automation.idempotency.enabled' => true,
            'corepanel.automation.retry.max_attempts' => 3,
            'corepanel.automation.retry.backoff_seconds' => [0, 0, 0],
            'corepanel.automation.scheduler.enabled' => true,
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.automation-acceptance',
            'corepanel.themes.auto_load_active' => false,
        ]);

        $this->withoutVite();

        $bus = app(AutomationEventBus::class);
        $bus->flush();
        app(WorkflowEngine::class)->register();
        app(RulesEngine::class)->register();
    }

    public function test_core_event_triggers_workflow_actions(): void
    {
        Workflow::factory()->create([
            'name' => 'On invoice paid',
            'trigger_event' => AutomationEvent::INVOICE_PAID,
            'conditions' => [
                'all' => [
                    ['field' => 'amount', 'operator' => 'gte', 'value' => 5],
                ],
            ],
            'steps' => [
                ['type' => 'set', 'key' => 'handled', 'value' => true],
                ['type' => 'log', 'message' => 'invoice-paid-accepted'],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '42.00',
            'subtotal' => '42.00',
        ]);

        event(new InvoicePaid($invoice->fresh() ?? $invoice));

        $log = AutomationLog::query()
            ->whereNotNull('workflow_id')
            ->where('trigger_event', AutomationEvent::INVOICE_PAID)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(AutomationLogStatus::Succeeded, $log->status);
        $this->assertSame($invoice->id, $log->payload['data']['invoice_id'] ?? null);
        $this->assertTrue($log->result['bag']['handled'] ?? false);
        $this->assertSame(['invoice-paid-accepted'], $log->result['bag']['_logs'] ?? null);
    }

    public function test_conditional_rule_applies_on_matching_event(): void
    {
        $service = Service::factory()->active()->create();

        AutomationRule::factory()->create([
            'name' => 'Suspend when overdue',
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [
                    ['field' => 'service_id', 'operator' => 'eq', 'value' => $service->id],
                ],
            ],
            'action_json' => [
                'type' => 'suspend_service',
                'service_id' => $service->id,
            ],
            'priority' => 5,
            'is_active' => true,
        ]);

        AutomationRule::factory()->create([
            'name' => 'Should not run',
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [
                    ['field' => 'service_id', 'operator' => 'eq', 'value' => 999999],
                ],
            ],
            'action_json' => ['type' => 'log', 'message' => 'should-not-run'],
            'priority' => 1,
            'is_active' => true,
        ]);

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '15.00',
            'subtotal' => '15.00',
        ]);

        // Bridge payload includes invoice fields; inject service_id via custom listener path:
        // dispatch through bus with enriched data matching rule conditions.
        app(AutomationEventBus::class)->dispatch(AutomationEvent::INVOICE_OVERDUE, [
            'invoice_id' => $invoice->id,
            'service_id' => $service->id,
            'amount' => 15.0,
        ]);

        $this->assertSame(ServiceStatus::Suspended, $service->fresh()->status);

        $this->assertDatabaseHas('automation_logs', [
            'automation_rule_id' => AutomationRule::query()->where('name', 'Suspend when overdue')->value('id'),
            'status' => AutomationLogStatus::Succeeded->value,
        ]);

        $this->assertDatabaseMissing('automation_logs', [
            'automation_rule_id' => AutomationRule::query()->where('name', 'Should not run')->value('id'),
        ]);
    }

    public function test_critical_automation_is_idempotent_for_the_same_event(): void
    {
        $runs = 0;

        app(WorkflowActionRegistry::class)->register(
            'count_accept',
            function () use (&$runs): array {
                $runs++;

                return ['runs' => $runs];
            },
        );

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::INVOICE_PAID,
            'steps' => [['type' => 'count_accept']],
            'is_active' => true,
        ]);

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '9.00',
            'subtotal' => '9.00',
        ]);

        event(new InvoicePaid($invoice->fresh() ?? $invoice));
        event(new InvoicePaid($invoice->fresh() ?? $invoice));

        $this->assertSame(1, $runs);
        $this->assertSame(1, AutomationLog::query()->whereNotNull('workflow_id')->count());
        $this->assertSame(
            AutomationLogStatus::Succeeded,
            AutomationLog::query()->whereNotNull('workflow_id')->first()?->status,
        );
    }

    public function test_failed_automation_is_retried_then_falls_back(): void
    {
        config([
            'corepanel.automation.retry.max_attempts' => 2,
            'corepanel.automation.retry.backoff_seconds' => [0, 0],
        ]);

        $attempts = 0;

        app(WorkflowActionRegistry::class)->register(
            'always_fail_accept',
            function () use (&$attempts): array {
                $attempts++;

                throw new \RuntimeException('accept-failure-'.$attempts);
            },
        );

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::NODE_OFFLINE,
            'steps' => [['type' => 'always_fail_accept']],
            'fallback' => [
                'type' => 'manual_intervention',
                'message' => 'Escalate node offline',
            ],
            'is_active' => true,
        ]);

        app(AutomationEventBus::class)->dispatch(AutomationEvent::NODE_OFFLINE, [
            'node_id' => 77,
        ]);

        $log = AutomationLog::query()->whereNotNull('workflow_id')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(AutomationLogStatus::Retrying, $log->status);
        $this->assertSame(1, $log->attempt);

        $log->forceFill(['next_retry_at' => now()->subSecond()])->save();
        $log = app(AutomationRetryService::class)->retry($log->fresh());

        $this->assertSame(AutomationLogStatus::Fallback, $log->status);
        $this->assertSame(2, $log->attempt);
        $this->assertTrue($log->result['fallback']['result']['manual_intervention'] ?? false);
        $this->assertSame('Escalate node offline', $log->result['fallback']['result']['message'] ?? null);
        $this->assertSame(2, $attempts);
    }

    public function test_scheduler_registers_system_jobs_and_admin_can_review_runs(): void
    {
        Artisan::call('schedule:list');
        $schedule = Artisan::output();

        foreach ([
            CheckInvoicesDueJob::class,
            EvaluateScheduledRulesJob::class,
            RetryFailedSystemJobsJob::class,
        ] as $job) {
            $this->assertStringContainsString($job, $schedule);
        }

        $workflow = Workflow::factory()->create([
            'name' => 'Acceptance visible workflow',
            'trigger_event' => AutomationEvent::TICKET_CREATED,
            'steps' => [['type' => 'noop']],
            'is_active' => true,
        ]);

        $rule = AutomationRule::factory()->create([
            'name' => 'Acceptance visible rule',
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [],
            ],
            'action_json' => ['type' => 'noop'],
            'is_active' => true,
        ]);

        app(AutomationEventBus::class)->dispatch(AutomationEvent::TICKET_CREATED, [
            'ticket_id' => 501,
        ]);

        $log = AutomationLog::query()
            ->where('workflow_id', $workflow->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(AutomationLogStatus::Succeeded, $log->status);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.automation.workflows.index'))
            ->assertOk()
            ->assertSee('Acceptance visible workflow');

        $this->actingAs($admin)
            ->get(route('admin.automation.rules.index'))
            ->assertOk()
            ->assertSee('Acceptance visible rule');

        $this->actingAs($admin)
            ->get(route('admin.automation.logs.index'))
            ->assertOk()
            ->assertSee('Acceptance visible workflow')
            ->assertSee(AutomationEvent::TICKET_CREATED);

        $this->actingAs($admin)
            ->get(route('admin.automation.logs.show', $log))
            ->assertOk()
            ->assertSee(__('Succeeded'))
            ->assertSee((string) $log->id);

        $this->assertSame(0, AutomationLog::query()->where('automation_rule_id', $rule->id)->count());
    }

    public function test_domain_invoice_overdue_event_feeds_rules_engine(): void
    {
        AutomationRule::factory()->create([
            'name' => 'Log overdue invoices',
            'condition_json' => [
                'event' => AutomationEvent::INVOICE_OVERDUE,
                'all' => [
                    ['field' => 'amount', 'operator' => 'gte', 'value' => 1],
                ],
            ],
            'action_json' => [
                'type' => 'log',
                'message' => 'overdue-accepted',
            ],
            'is_active' => true,
        ]);

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '18.50',
            'subtotal' => '18.50',
        ]);

        event(new InvoiceOverdue($invoice->fresh() ?? $invoice));

        $log = AutomationLog::query()
            ->whereNotNull('automation_rule_id')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(AutomationLogStatus::Succeeded, $log->status);
        $this->assertSame(['overdue-accepted'], $log->result['bag']['_logs'] ?? null);
        $this->assertSame($invoice->id, $log->payload['data']['invoice_id'] ?? null);
    }

    public function test_rule_and_workflow_engines_coexist_on_shared_bus_event(): void
    {
        $seen = [];

        app(WorkflowActionRegistry::class)->register(
            'wf_mark',
            function () use (&$seen): array {
                $seen[] = 'workflow';

                return [];
            },
        );
        app(RuleActionRegistry::class)->register(
            'rule_mark',
            function () use (&$seen): array {
                $seen[] = 'rule';

                return [];
            },
        );

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::SERVICE_SUSPENDED,
            'steps' => [['type' => 'wf_mark']],
            'priority' => 10,
        ]);

        AutomationRule::factory()->create([
            'condition_json' => [
                'event' => AutomationEvent::SERVICE_SUSPENDED,
                'all' => [],
            ],
            'action_json' => ['type' => 'rule_mark'],
            'priority' => 10,
        ]);

        app(AutomationEventBus::class)->dispatch(AutomationEvent::SERVICE_SUSPENDED, [
            'service_id' => 12,
        ]);

        $this->assertSame(['workflow', 'rule'], $seen);
        $this->assertSame(2, AutomationLog::query()->count());
        $this->assertSame(1, AutomationLog::query()->whereNotNull('workflow_id')->count());
        $this->assertSame(1, AutomationLog::query()->whereNotNull('automation_rule_id')->count());
    }
}
