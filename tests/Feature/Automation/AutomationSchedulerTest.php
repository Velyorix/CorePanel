<?php

namespace Tests\Feature\Automation;

use App\Jobs\GenerateRenewalInvoices;
use App\Jobs\ProcessOverdueSuspensions;
use App\Jobs\SendInvoiceReminders;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Jobs\CheckInvoicesDueJob;
use Core\Automation\Jobs\CleanupAutomationLogsJob;
use Core\Automation\Jobs\EvaluateScheduledRulesJob;
use Core\Automation\Jobs\GenerateBillingReportJob;
use Core\Automation\Jobs\GenerateSystemHealthReportJob;
use Core\Automation\Jobs\RetryFailedSystemJobsJob;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\AutomationRule;
use Core\Automation\Services\AutomationLogCleanupService;
use Core\Automation\Services\BillingReportService;
use Core\Automation\Services\FailedSystemJobRetryService;
use Core\Automation\Services\SystemHealthReportService;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Nodes\Jobs\CollectNodeMetricsJob;
use Core\Nodes\Jobs\RunNodeHealthChecksJob;
use Core\Sync\Jobs\NodeSyncJob;
use Core\Sync\Jobs\ServiceSyncJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AutomationSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_cron_jobs_are_registered_on_the_scheduler(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        foreach ([
            CheckInvoicesDueJob::class,
            GenerateRenewalInvoices::class,
            SendInvoiceReminders::class,
            ProcessOverdueSuspensions::class,
            RunNodeHealthChecksJob::class,
            CollectNodeMetricsJob::class,
            ServiceSyncJob::class,
            NodeSyncJob::class,
            EvaluateScheduledRulesJob::class,
            RetryFailedSystemJobsJob::class,
            CleanupAutomationLogsJob::class,
            GenerateBillingReportJob::class,
            GenerateSystemHealthReportJob::class,
        ] as $job) {
            $this->assertStringContainsString($job, $output);
        }
    }

    public function test_check_invoices_due_job_marks_overdue_invoices(): void
    {
        $invoice = Invoice::factory()->unpaid()->create([
            'due_at' => now()->subDays(2)->startOfDay(),
        ]);

        app(CheckInvoicesDueJob::class)->handle(app(\Core\Billing\Services\InvoiceReminderService::class));

        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_cleanup_automation_logs_prunes_old_entries(): void
    {
        config(['corepanel.automation.scheduler.log_retention_days' => 7]);

        $old = AutomationLog::factory()->succeeded()->create([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        $recent = AutomationLog::factory()->succeeded()->create([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $deleted = app(AutomationLogCleanupService::class)->prune();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('automation_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('automation_logs', ['id' => $recent->id]);
    }

    public function test_evaluate_scheduled_rules_job_runs_unscoped_rules(): void
    {
        config([
            'corepanel.automation.enabled' => true,
            'corepanel.api.webhooks.enabled' => false,
        ]);

        AutomationRule::factory()->create([
            'name' => 'Scheduler noop',
            'is_active' => true,
            'priority' => 10,
            'condition_json' => ['all' => []],
            'action_json' => ['type' => 'noop'],
        ]);

        app(EvaluateScheduledRulesJob::class)->handle(app(\Core\Automation\Services\RulesEngine::class));

        $this->assertDatabaseHas('automation_logs', [
            'trigger_event' => 'rule.evaluate',
            'status' => AutomationLogStatus::Succeeded->value,
        ]);
    }

    public function test_billing_and_health_reports_log_snapshots(): void
    {
        Log::spy();

        $billing = app(BillingReportService::class)->generateDaily();
        $health = app(SystemHealthReportService::class)->generateDaily();

        $this->assertArrayHasKey('unpaid', $billing);
        $this->assertArrayHasKey('nodes_total', $health);

        Log::shouldHaveReceived('info')->withArgs(function (string $message): bool {
            return $message === 'automation.report.billing';
        })->once();

        Log::shouldHaveReceived('info')->withArgs(function (string $message): bool {
            return $message === 'automation.report.system_health';
        })->once();
    }

    public function test_failed_system_job_retry_service_retries_allowed_classes(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $this->markTestSkipped('failed_jobs table is not available.');
        }

        $payload = json_encode([
            'uuid' => 'test-uuid-1',
            'displayName' => GenerateRenewalInvoices::class,
            'data' => [
                'command' => 'O:'.strlen(GenerateRenewalInvoices::class).':"'.GenerateRenewalInvoices::class.'":0:{}',
            ],
        ], JSON_THROW_ON_ERROR);

        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-1',
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => $payload,
            'exception' => 'boom',
            'failed_at' => now(),
        ]);

        $retried = app(FailedSystemJobRetryService::class)->retryDue(10);

        $this->assertSame(1, $retried);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => 'test-uuid-1']);
    }
}
