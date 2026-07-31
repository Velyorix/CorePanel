<?php

namespace Core\Providers\DataTransferObjects;

use Core\Providers\Enums\ProviderOperationStatus;

final readonly class ProvisioningResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ProviderOperationStatus $status,
        public ?string $externalId = null,
        public ?string $hostname = null,
        public ?string $ipAddress = null,
        public ?int $nodeId = null,
        public ?string $message = null,
        public array $payload = [],
    ) {
    }

    public static function success(
        ?string $externalId = null,
        ?string $hostname = null,
        ?string $ipAddress = null,
        ?int $nodeId = null,
        ?string $message = null,
        array $payload = [],
    ): self {
        return new self(
            ProviderOperationStatus::Success,
            $externalId,
            $hostname,
            $ipAddress,
            $nodeId,
            $message,
            $payload,
        );
    }

    public static function pending(
        ?string $message = null,
        array $payload = [],
        ?string $externalId = null,
    ): self {
        return new self(
            ProviderOperationStatus::Pending,
            $externalId,
            message: $message,
            payload: $payload,
        );
    }

    public static function skipped(
        ?string $message = null,
        array $payload = [],
        ?string $externalId = null,
    ): self {
        return new self(
            ProviderOperationStatus::Skipped,
            $externalId,
            message: $message,
            payload: $payload,
        );
    }

    public static function failed(?string $message = null, array $payload = []): self
    {
        return new self(
            ProviderOperationStatus::Failed,
            message: $message,
            payload: $payload,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }
}
