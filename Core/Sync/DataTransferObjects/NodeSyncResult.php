<?php

namespace Core\Sync\DataTransferObjects;

final readonly class NodeSyncResult
{
    public function __construct(
        public int $synced = 0,
        public int $failed = 0,
        public int $skipped = 0,
    ) {
    }

    public function total(): int
    {
        return $this->synced + $this->failed + $this->skipped;
    }
}
