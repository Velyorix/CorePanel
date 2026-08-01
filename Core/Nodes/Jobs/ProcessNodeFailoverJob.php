<?php

namespace Core\Nodes\Jobs;

use Core\Automation\Concerns\IdempotentUniqueJob;
use Core\Automation\Services\AutomationIdempotencyKey;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeFailoverService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessNodeFailoverJob implements ShouldBeUnique, ShouldQueue
{
    use IdempotentUniqueJob;
    use Queueable;

    public function __construct(
        public readonly int $nodeId,
    ) {
        $this->configureUniqueFor();
    }

    public function idempotencyKey(): string
    {
        return app(AutomationIdempotencyKey::class)->forJob(self::class, [
            'node_id' => $this->nodeId,
        ]);
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
