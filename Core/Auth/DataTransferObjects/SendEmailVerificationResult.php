<?php

namespace Core\Auth\DataTransferObjects;

readonly class SendEmailVerificationResult
{
    private function __construct(
        public bool $sent,
        public ?string $failureReason = null,
    ) {
    }

    public static function sent(): self
    {
        return new self(sent: true);
    }

    public static function skipped(): self
    {
        return new self(sent: false);
    }

    public static function rateLimited(int $seconds): self
    {
        return new self(sent: false, failureReason: 'rate_limited:'.$seconds);
    }
}
