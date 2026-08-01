<?php

namespace Tests\Feature\Automation;

use App\Jobs\GenerateRenewalInvoices;
use App\Jobs\ProcessOverdueSuspensions;
use App\Jobs\SendInvoiceReminders;
use Core\Automation\DataTransferObjects\AutomationEventContext;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Jobs\CheckInvoicesDueJob;
use Core\Automation\Jobs\CleanupAutomationLogsJob;
use Core\Automation\Jobs\EvaluateScheduledRulesJob;
use Core\Automation\Jobs\GenerateBillingReportJob;
use Core\Automation\Jobs\GenerateSystemHealthReportJob;
use Core\Automation\Jobs\RetryFailedSystemJobsJob;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\AutomationRule;
use Core\Automation\Models\Workflow;
use Core\Automation\Services\AutomationEventBus;
use Core\Automation\Services\AutomationIdempotencyKey;
use Core\Automation\Services\RulesEngine;
use Core\Automation\Services\WorkflowActionRegistry;
use Core\Automation\Services\WorkflowEngine;
use Core\Automation\Support\AutomationEvent;
use Core\Nodes\Jobs\ProcessNodeFailoverJob;
use Core\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.automation.enabled' => true,
            'corepanel.api.webhooks.enabled' => false,
            'corepanel.automation.idempotency.enabled' => true,
        ]);

        app(AutomationEventBus::class)->flush();
        app(WorkflowEngine::class)->register();
        app(RulesEngine::class)->register();
    }

    public function test_duplicate_workflow_event_does_not_rerun_actions(): void
    {
        $runs = 0;

        app(WorkflowActionRegistry::class)->register(
            'count',
            function () use (&$runs): array {
                $runs++;

                return ['runs' => $runs];
            },
        );

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::INVOICE_PAID,
            'steps' => [['type' => 'count']],
        ]);

        $context = AutomationEventContext::make(
            AutomationEvent::INVOICE_PAID,
            ['invoice_id' => 42, 'amount' => 10],
        );

        $first = app(WorkflowEngine::class)->handle($context)->first();
        $second = app(WorkflowEngine::class)->handle($context)->first();

        $this->assertSame(1, $runs);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(AutomationLogStatus::Succeeded, $first->status);
        $this->assertSame(1, AutomationLog::query()->count());
        $this->assertStringStartsWith('workflow:', (string) $first->idempotency_key);
    }

    public function test_duplicate_rule_event_is_deduplicated(): void
    {
        $runs = 0;

        app(\Core\Automation\Services\RuleActionRegistry::class)->register(
            'count_rule',
            function () use (&$runs): array {
                $runs++;

                return ['ok' => true];
            },
        );

        AutomationRule::factory()->create([
            'condition_json' => [
                'event' => AutomationEvent::SERVICE_SUSPENDED,
                'all' => [],
            ],
            'action_json' => ['type' => 'count_rule'],
        ]);

        $data = ['service_id' => 7];

        $first = app(RulesEngine::class)->evaluate($data, AutomationEvent::SERVICE_SUSPENDED)->first();
        $second = app(RulesEngine::class)->evaluate($data, AutomationEvent::SERVICE_SUSPENDED)->first();

        $this->assertSame(1, $runs);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AutomationLog::query()->count());
    }

    public function test_failed_run_releases_key_so_replay_can_claim_again(): void
    {
        config(['corepanel.automation.retry.max_attempts' => 1]);

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::TICKET_CREATED,
            'steps' => [['type' => 'does_not_exist']],
        ]);

        $context = AutomationEventContext::make(
            AutomationEvent::TICKET_CREATED,
            ['ticket_id' => 55],
        );

        $failed = app(WorkflowEngine::class)->handle($context)->first();
        $this->assertSame(AutomationLogStatus::Failed, $failed->status);
        $this->assertStringStartsWith('released:', (string) $failed->idempotency_key);

        Workflow::query()->update([
            'steps' => [['type' => 'noop']],
        ]);

        $replay = app(WorkflowEngine::class)->handle($context)->first();

        $this->assertSame(AutomationLogStatus::Succeeded, $replay->status);
        $this->assertNotSame($failed->id, $replay->id);
        $this->assertSame(2, AutomationLog::query()->count());
    }

    public function test_critical_jobs_implement_should_be_unique_with_stable_keys(): void
    {
        $jobs = [
            new CheckInvoicesDueJob,
            new EvaluateScheduledRulesJob,
            new CleanupAutomationLogsJob,
            new GenerateBillingReportJob,
            new GenerateSystemHealthReportJob,
            new RetryFailedSystemJobsJob,
            new GenerateRenewalInvoices,
            new SendInvoiceReminders,
            new ProcessOverdueSuspensions,
            new DeliverWebhookJob(15),
            new ProcessNodeFailoverJob(3),
        ];

        foreach ($jobs as $job) {
            $this->assertInstanceOf(ShouldBeUnique::class, $job);
            $this->assertNotSame('', $job->uniqueId());
            $this->assertStringStartsWith('job:', $job->uniqueId());
            $this->assertGreaterThan(0, $job->uniqueFor);
        }

        $this->assertSame(
            (new DeliverWebhookJob(15))->uniqueId(),
            (new DeliverWebhookJob(15))->uniqueId(),
        );
        $this->assertNotSame(
            (new DeliverWebhookJob(15))->uniqueId(),
            (new DeliverWebhookJob(16))->uniqueId(),
        );
    }

    public function test_unique_job_is_not_queued_twice(): void
    {
        Queue::fake();

        CheckInvoicesDueJob::dispatch();
        CheckInvoicesDueJob::dispatch();

        Queue::assertPushed(CheckInvoicesDueJob::class, 1);
    }

    public function test_idempotency_key_builder_fingerprints_entity_ids(): void
    {
        $keys = app(AutomationIdempotencyKey::class);

        $a = $keys->forWorkflow(1, AutomationEvent::INVOICE_PAID, ['invoice_id' => 9]);
        $b = $keys->forWorkflow(1, AutomationEvent::INVOICE_PAID, ['invoice_id' => 9, 'amount' => 99]);
        $c = $keys->forWorkflow(1, AutomationEvent::INVOICE_PAID, ['invoice_id' => 10]);

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertTrue($keys->hasEntityFingerprint(['service_id' => 1]));
        $this->assertFalse($keys->hasEntityFingerprint(['amount' => 1]));
    }
}
