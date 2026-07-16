<?php

namespace Core\License\DataTransferObjects;

class LicenseValidationState
{
    public function __construct(
        public readonly bool $isValid,
        public readonly bool $inGracePeriod,
        public readonly string $status,
        public readonly ?string $reason,
        public readonly ?string $message,
        public readonly string $source,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toCachePayload(): array
    {
        return [
            'is_valid' => $this->isValid,
            'in_grace_period' => $this->inGracePeriod,
            'status' => $this->status,
            'reason' => $this->reason,
            'message' => $this->message,
            'source' => $this->source,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromCachePayload(array $payload): self
    {
        return new self(
            isValid: (bool) ($payload['is_valid'] ?? false),
            inGracePeriod: (bool) ($payload['in_grace_period'] ?? false),
            status: (string) ($payload['status'] ?? 'invalid'),
            reason: isset($payload['reason']) ? (string) $payload['reason'] : null,
            message: isset($payload['message']) ? (string) $payload['message'] : null,
            source: 'cache',
        );
    }
}

