<?php

namespace App\Jobs;

use Core\Automation\Concerns\IdempotentUniqueJob;
use Core\Automation\Services\AutomationIdempotencyKey;
use Core\Billing\Services\OverdueSuspensionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOverdueSuspensions implements ShouldBeUnique, ShouldQueue
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
            'slot' => now()->toDateString(),
        ]);
    }

    public function handle(OverdueSuspensionService $suspensions): void
    {
        $suspensions->process();
    }
}
