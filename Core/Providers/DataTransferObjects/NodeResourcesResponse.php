<?php

namespace Core\Providers\DataTransferObjects;

use Core\Providers\Enums\ProviderOperationStatus;

final readonly class NodeResourcesResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ProviderOperationStatus $status,
        public NodeResourcesData $resources,
        public array $payload = [],
        public ?string $message = null,
    ) {
    }

    public static function success(NodeResourcesData $resources, array $payload = []): self
    {
        return new self(ProviderOperationStatus::Success, $resources, $payload);
    }

    public static function failed(?string $message = null, array $payload = []): self
    {
        return new self(
            ProviderOperationStatus::Failed,
            new NodeResourcesData,
            $payload,
            $message,
        );
    }
}
