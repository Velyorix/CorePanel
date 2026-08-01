<?php

namespace Core\Automation\Jobs;

use Core\Automation\Concerns\IdempotentUniqueJob;
use Core\Automation\Services\AutomationIdempotencyKey;
use Core\Automation\Services\AutomationRetryService;
use Core\Automation\Services\FailedSystemJobRetryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryFailedSystemJobsJob implements ShouldBeUnique, ShouldQueue
{
    use IdempotentUniqueJob;
    use Queueable;

    public function __construct()
    {
        $this->configureUniqueFor();
    }

    public function idempotencyKey(): string
    {
        return app(AutomationIdempotencyKey::class)->forJob(self::class, [
            'slot' => now()->format('YmdHi'),
        ]);
    }

    public function handle(
        FailedSystemJobRetryService $queueRetries,
        AutomationRetryService $automationRetries,
    ): void {
        $queueRetries->retryDue();
        $automationRetries->processDue();
    }
}
