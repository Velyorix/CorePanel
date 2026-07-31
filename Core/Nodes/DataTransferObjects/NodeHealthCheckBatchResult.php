<?php

namespace Core\Nodes\DataTransferObjects;

final readonly class NodeHealthCheckBatchResult
{
    public function __construct(
        public int $online = 0,
        public int $degraded = 0,
        public int $offline = 0,
        public int $skipped = 0,
    ) {
    }

    public function total(): int
    {
        return $this->online + $this->degraded + $this->offline + $this->skipped;
    }
}
