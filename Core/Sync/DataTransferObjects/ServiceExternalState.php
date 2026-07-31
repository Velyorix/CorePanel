<?php

namespace Core\Sync\DataTransferObjects;

use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Services\Enums\ServiceStatus;

final readonly class ServiceExternalState
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ?bool $exists = null,
        public ?ServiceStatus $status = null,
        public ?string $externalId = null,
        public ?string $hostname = null,
        public ?string $ipAddress = null,
        public ?int $nodeId = null,
        public ?string $message = null,
        public array $payload = [],
    ) {
    }

    public static function fromProvisioningResponse(ProvisioningResponse $response): self
    {
        $payload = $response->payload;

        if (! $response->isSuccessful()) {
            if (self::indicatesMissingResource($payload, $response->message)) {
                return new self(
                    exists: false,
                    message: $response->message,
                    payload: $payload,
                );
            }

            return new self(
                message: $response->message,
                payload: $payload,
            );
        }

        $remoteStatus = self::extractRemoteStatus($payload);

        return new self(
            exists: true,
            status: self::mapRemoteStatus($remoteStatus),
            externalId: filled($response->externalId) ? (string) $response->externalId : null,
            hostname: filled($response->hostname) ? (string) $response->hostname : null,
            ipAddress: filled($response->ipAddress) ? (string) $response->ipAddress : null,
            nodeId: $response->nodeId,
            message: $response->message,
            payload: $payload,
        );
    }

    public function isComparable(): bool
    {
        return $this->exists !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function indicatesMissingResource(array $payload, ?string $message): bool
    {
        $reason = strtolower(trim((string) ($payload['reason'] ?? $payload['remote_status'] ?? '')));

        if (in_array($reason, ['not_found', 'deleted', 'missing', 'removed'], true)) {
            return true;
        }

        $message = strtolower(trim((string) $message));

        return $message !== '' && (
            str_contains($message, 'not found')
            || str_contains($message, 'does not exist')
            || str_contains($message, 'no longer exists')
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function extractRemoteStatus(array $payload): ?string
    {
        $value = $payload['remote_status'] ?? $payload['status'] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return $value === '' ? null : $value;
    }

    private static function mapRemoteStatus(?string $remoteStatus): ?ServiceStatus
    {
        return match ($remoteStatus) {
            'active', 'running', 'online' => ServiceStatus::Active,
            'suspended', 'paused', 'offline' => ServiceStatus::Suspended,
            'terminated', 'deleted', 'removed' => ServiceStatus::Terminated,
            'failed', 'error' => ServiceStatus::Failed,
            'pending', 'provisioning', 'installing' => ServiceStatus::Provisioning,
            'cancelled', 'canceled' => ServiceStatus::Cancelled,
            default => null,
        };
    }
}
