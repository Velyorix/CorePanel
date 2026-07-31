<?php

namespace Core\Automation\Jobs;

use Core\Automation\Services\FailedSystemJobRetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryFailedSystemJobsJob implements ShouldQueue
{
    use Queueable;

    public function handle(FailedSystemJobRetryService $retries): void
    {
        $retries->retryDue();
    }
}
