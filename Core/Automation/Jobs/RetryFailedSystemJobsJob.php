<?php

namespace Core\Automation\Jobs;

use Core\Automation\Services\AutomationRetryService;
use Core\Automation\Services\FailedSystemJobRetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryFailedSystemJobsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        FailedSystemJobRetryService $queueRetries,
        AutomationRetryService $automationRetries,
    ): void {
        $queueRetries->retryDue();
        $automationRetries->processDue();
    }
}
