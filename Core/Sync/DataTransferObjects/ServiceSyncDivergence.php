<?php

namespace Core\Sync\DataTransferObjects;

use Core\Sync\Enums\ServiceSyncDivergenceType;

final readonly class ServiceSyncDivergence
{
    public function __construct(
        public ServiceSyncDivergenceType $type,
        public ?string $local = null,
        public ?string $remote = null,
        public ?string $message = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type->value,
            'local' => $this->local,
            'remote' => $this->remote,
            'message' => $this->message,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
