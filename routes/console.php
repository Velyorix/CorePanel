<?php

use App\Jobs\ValidateLicenseJob;
use App\Jobs\GenerateRenewalInvoices;
use App\Jobs\ProcessOverdueSuspensions;
use App\Jobs\SendInvoiceReminders;
use Core\Nodes\Jobs\CollectNodeMetricsJob;
use Core\Nodes\Jobs\RunNodeHealthChecksJob;
use Core\Sync\Jobs\NodeSyncJob;
use Core\Sync\Jobs\ServiceSyncJob;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeTelemetryService;
use Core\Sync\Services\NodeSyncService;
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

$serviceSyncSchedule = (string) config('corepanel.services.sync.schedule', 'everyFiveMinutes');

$serviceSync = Schedule::job(new ServiceSyncJob)->withoutOverlapping();

match ($serviceSyncSchedule) {
    'everyMinute' => $serviceSync->everyMinute(),
    'everyTenMinutes' => $serviceSync->everyTenMinutes(),
    'hourly' => $serviceSync->hourly(),
    default => $serviceSync->everyFiveMinutes(),
};

$nodeSyncSchedule = (string) config('corepanel.nodes.sync.schedule', 'everyTenMinutes');

$nodeSync = Schedule::job(new NodeSyncJob)->withoutOverlapping();

match ($nodeSyncSchedule) {
    'everyFiveMinutes' => $nodeSync->everyFiveMinutes(),
    'everyMinute' => $nodeSync->everyMinute(),
    'hourly' => $nodeSync->hourly(),
    default => $nodeSync->everyTenMinutes(),
};

Artisan::command('nodes:telemetry {node?}', function (?string $node = null) {
    $telemetry = app(NodeTelemetryService::class);

    $nodes = $node === null
        ? Node::query()->whereNotNull('module')->where('module', '!=', '')->get()
        : Node::query()->whereKey($node)->get();

    if ($nodes->isEmpty()) {
        $this->error('No matching nodes found.');

        return 1;
    }

    foreach ($nodes as $matched) {
        $telemetry->refresh($matched);
        $this->info("Refreshed telemetry for node [{$matched->id}] {$matched->name}");
    }

    return 0;
})->purpose('Refresh node health checks and metrics');

Artisan::command('nodes:sync {node?}', function (?string $node = null) {
    $sync = app(NodeSyncService::class);

    $nodes = $node === null
        ? Node::query()->whereNotNull('module')->where('module', '!=', '')->get()
        : Node::query()->whereKey($node)->get();

    if ($nodes->isEmpty()) {
        $this->error('No matching nodes found.');

        return 1;
    }

    if ($node !== null) {
        $matched = $nodes->first();
        $outcome = $sync->syncNode($matched);

        $this->line("Node [{$matched->id}] {$matched->name}: {$outcome}");

        return $outcome === 'failed' ? 1 : 0;
    }

    $result = $sync->syncAll();

    $this->info("Synced {$result->synced} node(s), {$result->failed} failed, {$result->skipped} skipped.");

    return $result->failed > 0 ? 1 : 0;
})->purpose('Sync node resources from external providers');