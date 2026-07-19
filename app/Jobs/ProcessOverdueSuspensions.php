<?php

namespace App\Jobs;

use Core\Billing\Services\OverdueSuspensionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOverdueSuspensions implements ShouldQueue
{
    use Queueable;

    public function handle(OverdueSuspensionService $suspensions): void
    {
        $suspensions->process();
    }
}
