<?php

namespace Core\Nodes\DataTransferObjects;

final readonly class NodeMetricsCollectionResult
{
    public function __construct(
        public int $collected = 0,
        public int $failed = 0,
        public int $skipped = 0,
    ) {
    }

    public function total(): int
    {
        return $this->collected + $this->failed + $this->skipped;
    }
}
