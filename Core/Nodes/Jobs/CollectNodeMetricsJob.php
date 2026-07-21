<?php

namespace Core\Nodes\Jobs;

use Core\Nodes\Services\NodeMetricsCollectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CollectNodeMetricsJob implements ShouldQueue
{
    use Queueable;

    public function handle(NodeMetricsCollectionService $collector): void
    {
        $collector->collectAll();
    }
}
