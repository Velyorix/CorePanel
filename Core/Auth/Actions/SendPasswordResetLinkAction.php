<?php

namespace Core\Auth\Actions;

use Core\Auth\DataTransferObjects\PasswordResetLinkResult;
use Core\Auth\Models\User;
use Core\Auth\Services\PasswordResetRateLimiter;
use Illuminate\Support\Facades\Password;

class SendPasswordResetLinkAction
{
    public function __construct(
        private readonly PasswordResetRateLimiter $passwordResetRateLimiter,
    ) {
    }

    public function execute(string $email, string $ipAddress): PasswordResetLinkResult
    {
        if ($this->passwordResetRateLimiter->tooManyAttempts($email, $ipAddress)) {
            return PasswordResetLinkResult::rateLimited(
                $this->passwordResetRateLimiter->availableIn($email, $ipAddress),
            );
        }

        $this->passwordResetRateLimiter->hit($email, $ipAddress);

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! in_array($user->status, ['active', 'locked'], true)) {
            return PasswordResetLinkResult::sent();
        }

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            return PasswordResetLinkResult::throttled();
        }

        return PasswordResetLinkResult::sent();
    }
}
