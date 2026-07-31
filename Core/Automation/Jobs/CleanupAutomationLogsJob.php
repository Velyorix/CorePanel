<?php

namespace Core\Automation\Jobs;

use Core\Automation\Services\AutomationLogCleanupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupAutomationLogsJob implements ShouldQueue
{
    use Queueable;

    public function handle(AutomationLogCleanupService $cleanup): void
    {
        $cleanup->prune();
    }
}
