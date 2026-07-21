<?php

use App\Jobs\ValidateLicenseJob;
use App\Jobs\GenerateRenewalInvoices;
use App\Jobs\ProcessOverdueSuspensions;
use App\Jobs\SendInvoiceReminders;
use Core\Nodes\Jobs\CollectNodeMetricsJob;
use Core\Nodes\Jobs\RunNodeHealthChecksJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ValidateLicenseJob)
    ->everySixHours()
    ->withoutOverlapping();

$renewalSchedule = (string) config('corepanel.billing.renewal.schedule', 'daily');

$renewal = Schedule::job(new GenerateRenewalInvoices)->withoutOverlapping();

match ($renewalSchedule) {
    'hourly' => $renewal->hourly(),
    default => $renewal->daily(),
};

$reminderSchedule = (string) config('corepanel.billing.reminders.schedule', 'daily');

$reminders = Schedule::job(new SendInvoiceReminders)->withoutOverlapping();

match ($reminderSchedule) {
    'hourly' => $reminders->hourly(),
    default => $reminders->daily(),
};

$suspensionSchedule = (string) config('corepanel.billing.suspension.schedule', 'daily');

$suspensions = Schedule::job(new ProcessOverdueSuspensions)->withoutOverlapping();

match ($suspensionSchedule) {
    'hourly' => $suspensions->hourly(),
    default => $suspensions->daily(),
};

$nodeMetricsSchedule = (string) config('corepanel.nodes.metrics.schedule', 'everyFiveMinutes');

$nodeMetrics = Schedule::job(new CollectNodeMetricsJob)->withoutOverlapping();

match ($nodeMetricsSchedule) {
    'everyMinute' => $nodeMetrics->everyMinute(),
    'everyTenMinutes' => $nodeMetrics->everyTenMinutes(),
    'hourly' => $nodeMetrics->hourly(),
    default => $nodeMetrics->everyFiveMinutes(),
};

$nodeHealthSchedule = (string) config('corepanel.nodes.health.schedule', 'everyMinute');

$nodeHealth = Schedule::job(new RunNodeHealthChecksJob)->withoutOverlapping();

match ($nodeHealthSchedule) {
    'everyFiveMinutes' => $nodeHealth->everyFiveMinutes(),
    'everyTenMinutes' => $nodeHealth->everyTenMinutes(),
    'hourly' => $nodeHealth->hourly(),
    default => $nodeHealth->everyMinute(),
};