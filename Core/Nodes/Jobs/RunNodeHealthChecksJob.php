<?php

namespace Core\Nodes\Jobs;

use Core\Nodes\Services\NodeHealthCheckService;
use Illuminate\Foundation\Queue\Queueable;

class RunNodeHealthChecksJob
{
    use Queueable;

    public function handle(NodeHealthCheckService $healthChecks): void
    {
        $healthChecks->checkAll();
    }
}
