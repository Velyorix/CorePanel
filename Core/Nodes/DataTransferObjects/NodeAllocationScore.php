<?php

namespace Core\Nodes\DataTransferObjects;

use Core\Nodes\Models\Node;

final readonly class NodeAllocationScore
{
    public function __construct(
        public Node $node,
        public float $utilization,
        public int $allocatedServices,
        public int $sortOrder,
    ) {
    }
}
