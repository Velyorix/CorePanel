<?php

namespace Core\Nodes\DataTransferObjects;

use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Models\Node;

final readonly class NodeHealthSnapshot
{
    public function __construct(
        public NodeHealthState $state = NodeHealthState::Unknown,
        public ?int $latencyMs = null,
        public ?string $message = null,
        public ?string $checkedAt = null,
        public bool $autoManaged = false,
    ) {
    }

    /**
     * @param  array<string, mixed>  $health
     */
    public static function fromArray(array $health): self
    {
        $stateValue = isset($health['state']) ? (string) $health['state'] : NodeHealthState::Unknown->value;
        $state = NodeHealthState::tryFrom($stateValue) ?? NodeHealthState::Unknown;

        return new self(
            state: $state,
            latencyMs: isset($health['latency_ms']) ? (int) $health['latency_ms'] : null,
            message: isset($health['message']) ? (string) $health['message'] : null,
            checkedAt: isset($health['checked_at']) ? (string) $health['checked_at'] : null,
            autoManaged: (bool) ($health['auto_managed'] ?? false),
        );
    }

    public static function fromNode(Node $node): self
    {
        $health = is_array($node->config['health'] ?? null)
            ? $node->config['health']
            : [];

        return self::fromArray($health);
    }
}
