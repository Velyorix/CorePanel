<?php

namespace Core\Automation\Jobs;

use Core\Automation\Services\SystemHealthReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateSystemHealthReportJob implements ShouldQueue
{
    use Queueable;

    public function handle(SystemHealthReportService $reports): void
    {
        $reports->generateDaily();
    }
}
