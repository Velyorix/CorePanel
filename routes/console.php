<?php

use App\Jobs\ValidateLicenseJob;
use Core\Automation\Scheduling\AutomationScheduleRegistrar;
use Core\Marketplace\Jobs\CheckMarketplaceUpdatesJob;
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

$marketplaceUpdatesSchedule = (string) config('corepanel.marketplace.updates.schedule', 'daily');

$marketplaceUpdates = Schedule::job(new CheckMarketplaceUpdatesJob)->withoutOverlapping();

match ($marketplaceUpdatesSchedule) {
    'hourly' => $marketplaceUpdates->hourly(),
    'everySixHours' => $marketplaceUpdates->everySixHours(),
    default => $marketplaceUpdates->daily(),
};

app(AutomationScheduleRegistrar::class)->register();

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
