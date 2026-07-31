<?php

namespace Core\Nodes\DataTransferObjects;

final readonly class NodeFailoverBatchResult
{
    public function __construct(
        public int $reassigned = 0,
        public int $failed = 0,
        public int $skipped = 0,
    ) {
    }

    public function total(): int
    {
        return $this->reassigned + $this->failed + $this->skipped;
    }
}
