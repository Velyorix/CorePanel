<?php

namespace Core\Auth\Services;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EmailVerificationGate
{
    public function isRequired(): bool
    {
        return (bool) config('corepanel.auth.email_verification.required', true);
    }

    public function expireMinutes(): int
    {
        return (int) config('corepanel.auth.email_verification.expire_minutes', 60);
    }
}
