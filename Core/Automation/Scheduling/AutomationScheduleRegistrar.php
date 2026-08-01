<?php

namespace Core\Automation\Scheduling;

use App\Jobs\GenerateRenewalInvoices;
use App\Jobs\ProcessOverdueSuspensions;
use App\Jobs\SendInvoiceReminders;
use Core\Automation\Jobs\CheckInvoicesDueJob;
use Core\Automation\Jobs\CleanupAutomationLogsJob;
use Core\Automation\Jobs\EvaluateScheduledRulesJob;
use Core\Automation\Jobs\GenerateBillingReportJob;
use Core\Automation\Jobs\GenerateSystemHealthReportJob;
use Core\Automation\Jobs\RetryFailedSystemJobsJob;
use Core\Nodes\Jobs\CollectNodeMetricsJob;
use Core\Nodes\Jobs\RunNodeHealthChecksJob;
use Core\Sync\Jobs\NodeSyncJob;
use Core\Sync\Jobs\ServiceSyncJob;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Schedule;

/**
 * Registers system cron jobs (billing, health, sync, cleanup, reports).
 */
class AutomationScheduleRegistrar
{
    public function register(): void
    {
        if (! (bool) config('corepanel.automation.scheduler.enabled', true)) {
            return;
        }

        $this->scheduleJob(
            new CheckInvoicesDueJob,
            (string) config('corepanel.automation.scheduler.invoices_due', 'everyMinute'),
        );

        $this->scheduleJob(
            new GenerateRenewalInvoices,
            (string) config('corepanel.billing.renewal.schedule', 'daily'),
        );

        $this->scheduleJob(
            new SendInvoiceReminders,
            (string) config('corepanel.billing.reminders.schedule', 'daily'),
        );

        $this->scheduleJob(
            new ProcessOverdueSuspensions,
            (string) config('corepanel.billing.suspension.schedule', 'daily'),
        );

        $this->scheduleJob(
            new RunNodeHealthChecksJob,
            (string) config('corepanel.nodes.health.schedule', 'everyMinute'),
        );

        $this->scheduleJob(
            new CollectNodeMetricsJob,
            (string) config('corepanel.nodes.metrics.schedule', 'everyFiveMinutes'),
        );

        $this->scheduleJob(
            new ServiceSyncJob,
            (string) config('corepanel.services.sync.schedule', 'everyFiveMinutes'),
        );

        $this->scheduleJob(
            new NodeSyncJob,
            (string) config('corepanel.nodes.sync.schedule', 'everyTenMinutes'),
        );

        $this->scheduleJob(
            new EvaluateScheduledRulesJob,
            (string) config('corepanel.automation.scheduler.evaluate_rules', 'everyMinute'),
        );

        $this->scheduleJob(
            new RetryFailedSystemJobsJob,
            (string) config('corepanel.automation.scheduler.retry_failed', 'everyFiveMinutes'),
        );

        $this->scheduleJob(
            new CleanupAutomationLogsJob,
            (string) config('corepanel.automation.scheduler.cleanup_logs', 'hourly'),
        );

        $this->scheduleJob(
            new GenerateBillingReportJob,
            (string) config('corepanel.automation.scheduler.billing_report', 'daily'),
        );

        $this->scheduleJob(
            new GenerateSystemHealthReportJob,
            (string) config('corepanel.automation.scheduler.system_health_report', 'daily'),
        );
    }

    private function scheduleJob(object $job, string $frequency): void
    {
        $event = Schedule::job($job)->withoutOverlapping();

        $this->applyFrequency($event, $frequency);
    }

    private function applyFrequency(Event $event, string $frequency): void
    {
        match ($frequency) {
            'everyMinute' => $event->everyMinute(),
            'everyFiveMinutes' => $event->everyFiveMinutes(),
            'everyTenMinutes' => $event->everyTenMinutes(),
            'everySixHours' => $event->everySixHours(),
            'hourly' => $event->hourly(),
            'daily' => $event->daily(),
            default => $event->daily(),
        };
    }
}
