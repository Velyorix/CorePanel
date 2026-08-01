<?php

namespace Core\Automation\DataTransferObjects;

final readonly class WorkflowData
{
    /**
     * @param  array<string, mixed>|null  $conditions
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>|null  $fallback
     */
    public function __construct(
        public string $name,
        public ?string $slug,
        public string $triggerEvent,
        public ?array $conditions,
        public array $steps,
        public ?array $fallback,
        public int $priority,
        public bool $isActive,
    ) {
    }
}
