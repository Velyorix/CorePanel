<?php

namespace Core\Nodes\DataTransferObjects;

use Core\Nodes\Models\Node;

final readonly class NodeMonitoringNodeSummary
{
    public function __construct(
        public Node $node,
        public ?float $uptimePercent,
        public ?float $latestCpu,
        public ?float $latestRamMb,
        public ?float $latestDiskGb,
        public ?float $latestLoad,
    ) {
    }
}
