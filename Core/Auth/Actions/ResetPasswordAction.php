<?php

namespace Core\Auth\Actions;

use Core\Auth\DataTransferObjects\ResetPasswordResult;
use Core\Auth\Models\User;
use Core\Auth\Services\AccountLockoutService;
use Core\Auth\Services\LoginRateLimiter;
use Illuminate\Support\Facades\Password;

class ResetPasswordAction
{
    public function __construct(
        private readonly LoginRateLimiter $loginRateLimiter,
        private readonly AccountLockoutService $accountLockoutService,
    ) {
    }

    public function execute(string $email, string $password, string $token, string $ipAddress): ResetPasswordResult
    {
        $user = null;

        $status = Password::reset(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $resetUser, string $newPassword) use (&$user): void {
                $resetUser->forceFill(['password' => $newPassword])->save();
                $user = $resetUser;
            },
        );

        if ($status !== Password::PASSWORD_RESET || $user === null) {
            return ResetPasswordResult::failed($this->mapFailureReason($status));
        }

        $this->loginRateLimiter->clear($email, $ipAddress);
        $this->accountLockoutService->unlock($user);

        return ResetPasswordResult::success($user);
    }

    private function mapFailureReason(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => 'invalid_token',
            Password::INVALID_USER => 'invalid_user',
            default => 'unable_to_reset',
        };
    }
}
