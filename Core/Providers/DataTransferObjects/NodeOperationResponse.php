<?php

namespace Core\Providers\DataTransferObjects;

use Core\Providers\Enums\ProviderOperationStatus;

final readonly class NodeOperationResponse
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $changes
     */
    public function __construct(
        public ProviderOperationStatus $status,
        public ?string $message = null,
        public array $payload = [],
        public array $changes = [],
    ) {
    }

    public static function success(
        ?string $message = null,
        array $payload = [],
        array $changes = [],
    ): self {
        return new self(ProviderOperationStatus::Success, $message, $payload, $changes);
    }

    public static function failed(?string $message = null, array $payload = []): self
    {
        return new self(ProviderOperationStatus::Failed, $message, $payload);
    }
}
