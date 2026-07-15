<?php

namespace Core\Auth\Actions;

use Core\Auth\DataTransferObjects\SendEmailVerificationResult;
use Core\Auth\Models\User;
use Core\Auth\Services\EmailVerificationGate;
use Core\Auth\Services\EmailVerificationRateLimiter;

class SendEmailVerificationNotificationAction
{
    public function __construct(
        private readonly EmailVerificationGate $emailVerificationGate,
        private readonly EmailVerificationRateLimiter $emailVerificationRateLimiter,
    ) {
    }

    public function execute(User $user, string $ipAddress): SendEmailVerificationResult
    {
        if (! $this->emailVerificationGate->isRequired() || $user->hasVerifiedEmail()) {
            return SendEmailVerificationResult::skipped();
        }

        if ($this->emailVerificationRateLimiter->tooManyAttempts($user->email, $ipAddress)) {
            return SendEmailVerificationResult::rateLimited(
                $this->emailVerificationRateLimiter->availableIn($user->email, $ipAddress),
            );
        }

        $this->emailVerificationRateLimiter->hit($user->email, $ipAddress);

        $user->sendEmailVerificationNotification();

        return SendEmailVerificationResult::sent();
    }
}
