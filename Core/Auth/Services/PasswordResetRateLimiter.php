<?php

namespace Core\Auth\Services;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PasswordResetRateLimiter
{
    public function key(string $email, string $ipAddress): string
    {
        return 'password-reset|'.Str::transliterate(Str::lower($email).'|'.$ipAddress);
    }

    public function tooManyAttempts(string $email, string $ipAddress): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->key($email, $ipAddress),
            $this->maxAttempts(),
        );
    }

    public function hit(string $email, string $ipAddress): void
    {
        RateLimiter::hit(
            $this->key($email, $ipAddress),
            $this->decaySeconds(),
        );
    }

    public function availableIn(string $email, string $ipAddress): int
    {
        return RateLimiter::availableIn($this->key($email, $ipAddress));
    }

    public function maxAttempts(): int
    {
        return (int) config('corepanel.auth.password_reset.max_attempts', 5);
    }

    public function decaySeconds(): int
    {
        return (int) config('corepanel.auth.password_reset.decay_seconds', 60);
    }
}
