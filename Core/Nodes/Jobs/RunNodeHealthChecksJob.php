<?php

namespace Core\Nodes\Jobs;

use Core\Nodes\Services\NodeHealthCheckService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunNodeHealthChecksJob implements ShouldQueue
{
    use Queueable;

    public function handle(NodeHealthCheckService $healthChecks): void
    {
        $healthChecks->checkAll();
    }
}
