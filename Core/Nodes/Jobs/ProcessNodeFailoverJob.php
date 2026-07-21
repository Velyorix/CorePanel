<?php

namespace Core\Nodes\Jobs;

use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeFailoverService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessNodeFailoverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $nodeId,
    ) {
    }

    public function handle(NodeFailoverService $failover): void
    {
        $node = Node::query()->find($this->nodeId);

        if ($node === null) {
            return;
        }

        $failover->processNode($node);
    }
}
