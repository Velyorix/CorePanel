<?php

namespace Core\Automation\DataTransferObjects;

use DateTimeInterface;

/**
 * Normalized payload delivered to automation bus listeners.
 */
final class AutomationEventContext
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $event,
        public readonly array $data = [],
        public readonly ?object $source = null,
        public readonly DateTimeInterface $occurredAt = new \DateTimeImmutable,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(
        string $event,
        array $data = [],
        ?object $source = null,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            event: $event,
            data: $data,
            source: $source,
            occurredAt: $occurredAt ?? now(),
        );
    }
}
