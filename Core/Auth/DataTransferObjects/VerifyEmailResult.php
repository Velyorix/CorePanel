<?php

namespace Core\Auth\DataTransferObjects;

readonly class VerifyEmailResult
{
    private function __construct(
        public bool $successful,
        public bool $alreadyVerified = false,
        public ?string $failureReason = null,
    ) {
    }

    public static function success(): self
    {
        return new self(successful: true);
    }

    public static function alreadyVerified(): self
    {
        return new self(successful: true, alreadyVerified: true);
    }

    public static function failed(string $reason): self
    {
        return new self(successful: false, failureReason: $reason);
    }
}
