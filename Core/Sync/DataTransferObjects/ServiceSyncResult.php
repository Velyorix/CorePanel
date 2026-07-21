<?php

namespace Core\Sync\DataTransferObjects;

final readonly class ServiceSyncResult
{
    public function __construct(
        public int $polled = 0,
        public int $diverged = 0,
        public int $failed = 0,
        public int $skipped = 0,
    ) {
    }

    public function total(): int
    {
        return $this->polled + $this->diverged + $this->failed + $this->skipped;
    }
}
