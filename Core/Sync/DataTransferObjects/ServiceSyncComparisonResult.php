<?php

namespace Core\Sync\DataTransferObjects;

final readonly class ServiceSyncComparisonResult
{
    /**
     * @param  list<ServiceSyncDivergence>  $divergences
     */
    public function __construct(
        public ServiceExternalState $external,
        public array $divergences = [],
    ) {
    }

    public function hasDivergences(): bool
    {
        return $this->divergences !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function divergenceArrays(): array
    {
        return array_map(
            static fn (ServiceSyncDivergence $divergence): array => $divergence->toArray(),
            $this->divergences,
        );
    }
}
