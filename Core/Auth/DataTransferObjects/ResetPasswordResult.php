<?php

namespace Core\Auth\DataTransferObjects;

use Core\Auth\Models\User;

readonly class ResetPasswordResult
{
    private function __construct(
        public bool $successful,
        public ?User $user = null,
        public ?string $failureReason = null,
    ) {
    }

    public static function success(User $user): self
    {
        return new self(successful: true, user: $user);
    }

    public static function failed(string $reason): self
    {
        return new self(successful: false, failureReason: $reason);
    }
}
