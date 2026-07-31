<?php

namespace Core\Nodes\DataTransferObjects;

final readonly class NodeFailoverResult
{
    public function __construct(
        public int $serviceId,
        public int $fromNodeId,
        public ?int $toNodeId = null,
        public bool $reassigned = false,
        public bool $providerSynced = false,
        public bool $skipped = false,
        public ?string $message = null,
    ) {
    }
}
