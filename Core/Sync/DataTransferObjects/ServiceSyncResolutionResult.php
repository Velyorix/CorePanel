<?php

namespace Core\Sync\DataTransferObjects;

use Core\Sync\Enums\ServiceSyncResolutionAction;

final readonly class ServiceSyncResolutionResult
{
    /**
     * @param  list<array<string, mixed>>  $applied
     * @param  list<ServiceSyncDivergence>  $unresolved
     */
    public function __construct(
        public array $applied = [],
        public array $unresolved = [],
    ) {
    }

    public function isFullyResolved(): bool
    {
        return $this->unresolved === [] && $this->applied !== [];
    }

    public function hasApplied(): bool
    {
        return $this->applied !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function appliedArrays(): array
    {
        return $this->applied;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unresolvedArrays(): array
    {
        return array_map(
            static fn (ServiceSyncDivergence $divergence): array => $divergence->toArray(),
            $this->unresolved,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function appliedEntry(ServiceSyncResolutionAction $action, array $context = []): array
    {
        return array_filter([
            'action' => $action->value,
            ...$context,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
