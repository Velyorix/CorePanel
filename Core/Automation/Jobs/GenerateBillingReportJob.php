<?php

namespace Core\Automation\Jobs;

use Core\Automation\Services\BillingReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateBillingReportJob implements ShouldQueue
{
    use Queueable;

    public function handle(BillingReportService $reports): void
    {
        $reports->generateDaily();
    }
}
