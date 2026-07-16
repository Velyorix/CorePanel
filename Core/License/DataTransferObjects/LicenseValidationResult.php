<?php

namespace Core\License\DataTransferObjects;

class LicenseValidationResult
{
    /**
     * @param  array<string, mixed>  $license
     * @param  array<string, mixed>  $activation
     * @param  list<array<string, mixed>>  $entitlements
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $valid,
        public readonly ?int $statusCode,
        public readonly ?string $reason,
        public readonly ?string $message,
        public readonly array $license,
        public readonly array $activation,
        public readonly array $entitlements,
        public readonly array $raw,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload, ?int $statusCode): self
    {
        return new self(
            valid: (bool) ($payload['valid'] ?? false),
            statusCode: $statusCode,
            reason: isset($payload['reason']) ? (string) $payload['reason'] : null,
            message: isset($payload['message']) ? (string) $payload['message'] : null,
            license: is_array($payload['license'] ?? null) ? $payload['license'] : [],
            activation: is_array($payload['activation'] ?? null) ? $payload['activation'] : [],
            entitlements: is_array($payload['entitlements'] ?? null) ? array_values($payload['entitlements']) : [],
            raw: $payload,
        );
    }

    public static function requestFailed(string $message): self
    {
        return new self(
            valid: false,
            statusCode: null,
            reason: 'request_failed',
            message: $message,
            license: [],
            activation: [],
            entitlements: [],
            raw: [],
        );
    }
}

