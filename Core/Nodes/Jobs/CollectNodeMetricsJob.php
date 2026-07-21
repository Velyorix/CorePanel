<?php

namespace Core\Nodes\Jobs;

use Core\Nodes\Services\NodeMetricsCollectionService;
use Illuminate\Foundation\Queue\Queueable;

class CollectNodeMetricsJob
{
    use Queueable;

    public function handle(NodeMetricsCollectionService $collector): void
    {
        $collector->collectAll();
    }
}
