<?php

namespace Core\Provisioning\DataTransferObjects;

use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;

final readonly class NodeSelectionResult
{
    public function __construct(
        public ?NodeGroup $group = null,
        public ?Node $node = null,
        public ?NodeConnectionRequest $connection = null,
    ) {
    }

    public function nodeId(): ?int
    {
        return $this->node?->id;
    }

    public function hasNode(): bool
    {
        return $this->node !== null;
    }

    public function hasGroup(): bool
    {
        return $this->group !== null;
    }
}
