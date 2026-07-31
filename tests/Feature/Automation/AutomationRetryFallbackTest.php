<?php

namespace Tests\Feature\Automation;

use Core\Automation\DataTransferObjects\AutomationEventContext;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Jobs\RetryFailedSystemJobsJob;
use Core\Automation\Models\AutomationRule;
use Core\Automation\Models\Workflow;
use Core\Automation\Services\AutomationEventBus;
use Core\Automation\Services\AutomationRetryService;
use Core\Automation\Services\FailedSystemJobRetryService;
use Core\Automation\Services\RulesEngine;
use Core\Automation\Services\WorkflowActionRegistry;
use Core\Automation\Services\WorkflowEngine;
use Core\Automation\Support\AutomationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationRetryFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.automation.enabled' => true,
            'corepanel.api.webhooks.enabled' => false,
            'corepanel.automation.retry.max_attempts' => 3,
            'corepanel.automation.retry.backoff_seconds' => [0, 60, 120],
        ]);

        app(AutomationEventBus::class)->flush();
        app(WorkflowEngine::class)->register();
        app(RulesEngine::class)->register();
    }

    public function test_workflow_retries_until_success(): void
    {
        $attempts = 0;

        app(WorkflowActionRegistry::class)->register(
            'flaky',
            function (array $step) use (&$attempts): array {
                $attempts++;

                if ($attempts < 3) {
                    throw new \RuntimeException('temporary failure');
                }

                return ['ok' => true, 'attempts' => $attempts];
            },
        );

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::INVOICE_PAID,
            'steps' => [['type' => 'flaky']],
        ]);

        $logs = app(WorkflowEngine::class)->handle(AutomationEventContext::make(
            AutomationEvent::INVOICE_PAID,
            ['invoice_id' => 1],
        ));

        $log = $logs->first();
        $this->assertSame(AutomationLogStatus::Retrying, $log->status);
        $this->assertSame(1, $log->attempt);

        $log->forceFill(['next_retry_at' => now()->subSecond()])->save();
        $log = app(AutomationRetryService::class)->retry($log->fresh());
        $this->assertSame(AutomationLogStatus::Retrying, $log->status);
        $this->assertSame(2, $log->attempt);

        $log->forceFill(['next_retry_at' => now()->subSecond()])->save();
        $log = app(AutomationRetryService::class)->retry($log->fresh());
        $this->assertSame(AutomationLogStatus::Succeeded, $log->status);
        $this->assertSame(3, $log->attempt);
        $this->assertNull($log->next_retry_at);
    }

    public function test_workflow_runs_fallback_after_retries_exhausted(): void
    {
        config(['corepanel.automation.retry.max_attempts' => 1]);

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::NODE_OFFLINE,
            'steps' => [['type' => 'does_not_exist']],
            'fallback' => [
                'type' => 'manual_intervention',
                'message' => 'Escalate node offline automation',
            ],
        ]);

        $logs = app(WorkflowEngine::class)->handle(AutomationEventContext::make(
            AutomationEvent::NODE_OFFLINE,
            ['node_id' => 9],
        ));

        $log = $logs->first();
        $this->assertSame(AutomationLogStatus::Fallback, $log->status);
        $this->assertTrue($log->result['fallback']['result']['manual_intervention'] ?? false);
        $this->assertSame(
            'Escalate node offline automation',
            $log->result['fallback']['result']['message'] ?? null,
        );
        $this->assertStringContainsString('does_not_exist', (string) $log->error_message);
    }

    public function test_rule_fallback_runs_when_primary_action_fails(): void
    {
        config(['corepanel.automation.retry.max_attempts' => 1]);

        AutomationRule::factory()->create([
            'condition_json' => [],
            'action_json' => [
                'type' => 'does_not_exist',
                'fallback' => [
                    'type' => 'log',
                    'message' => 'rule-fallback',
                ],
            ],
        ]);

        $logs = app(RulesEngine::class)->evaluate(['ok' => true]);
        $log = $logs->first();

        $this->assertSame(AutomationLogStatus::Fallback, $log->status);
        $this->assertSame('log', $log->result['fallback']['type'] ?? null);
        $this->assertSame(['rule-fallback'], $log->result['fallback']['bag']['_logs'] ?? null);
    }

    public function test_process_due_retries_ready_automation_logs(): void
    {
        $attempts = 0;

        app(WorkflowActionRegistry::class)->register(
            'once',
            function () use (&$attempts): array {
                $attempts++;

                if ($attempts === 1) {
                    throw new \RuntimeException('boom');
                }

                return ['ok' => true];
            },
        );

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::TICKET_CREATED,
            'steps' => [['type' => 'once']],
        ]);

        $log = app(WorkflowEngine::class)->handle(AutomationEventContext::make(
            AutomationEvent::TICKET_CREATED,
            ['ticket_id' => 1],
        ))->first();

        $this->assertSame(AutomationLogStatus::Retrying, $log->status);

        $log->forceFill(['next_retry_at' => now()->subMinute()])->save();

        $processed = app(AutomationRetryService::class)->processDue();

        $this->assertCount(1, $processed);
        $this->assertSame(AutomationLogStatus::Succeeded, $processed->first()->status);
        $this->assertSame(2, $processed->first()->attempt);
    }

    public function test_retry_job_processes_automation_retries(): void
    {
        $attempts = 0;
        app(WorkflowActionRegistry::class)->register(
            'job_flaky',
            function () use (&$attempts): array {
                $attempts++;
                if ($attempts === 1) {
                    throw new \RuntimeException('fail once');
                }

                return ['ok' => true];
            },
        );

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::SERVICE_SUSPENDED,
            'steps' => [['type' => 'job_flaky']],
        ]);

        $log = app(WorkflowEngine::class)->handle(AutomationEventContext::make(
            AutomationEvent::SERVICE_SUSPENDED,
            ['service_id' => 1],
        ))->first();

        $log->forceFill(['next_retry_at' => now()->subSecond()])->save();

        app(RetryFailedSystemJobsJob::class)->handle(
            app(FailedSystemJobRetryService::class),
            app(AutomationRetryService::class),
        );

        $this->assertSame(AutomationLogStatus::Succeeded, $log->fresh()->status);
    }

    public function test_fallback_failure_marks_log_as_failed(): void
    {
        config(['corepanel.automation.retry.max_attempts' => 1]);

        Workflow::factory()->create([
            'trigger_event' => AutomationEvent::TICKET_REPLIED,
            'steps' => [['type' => 'does_not_exist']],
            'fallback' => ['type' => 'also_missing'],
        ]);

        $log = app(WorkflowEngine::class)->handle(AutomationEventContext::make(
            AutomationEvent::TICKET_REPLIED,
            ['ticket_id' => 2],
        ))->first();

        $this->assertSame(AutomationLogStatus::Failed, $log->status);
        $this->assertArrayHasKey('fallback_error', $log->result ?? []);
        $this->assertArrayHasKey('primary_error', $log->result ?? []);
    }
}
