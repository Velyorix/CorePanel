<?php

namespace Core\Auth\DataTransferObjects;

readonly class PasswordResetLinkResult
{
    private function __construct(
        public bool $successful,
        public ?string $failureReason = null,
    ) {
    }

    public static function sent(): self
    {
        return new self(successful: true);
    }

    public static function throttled(): self
    {
        return new self(successful: false, failureReason: 'throttled');
    }

    public static function rateLimited(int $seconds): self
    {
        return new self(successful: false, failureReason: 'rate_limited:'.$seconds);
    }
}
