<?php

namespace Core\Auth\Actions;

use Core\Auth\DataTransferObjects\VerifyEmailResult;
use Core\Auth\Models\User;
use Core\Auth\Services\AccountLockoutService;
use Illuminate\Auth\Events\Verified;

class VerifyEmailAction
{
    public function __construct(
        private readonly AccountLockoutService $accountLockoutService,
    ) {
    }
    public function execute(User $user, int|string $userId, string $hash): VerifyEmailResult
    {
        if (! hash_equals((string) $user->getKey(), (string) $userId)) {
            return VerifyEmailResult::failed('invalid_user');
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return VerifyEmailResult::failed('invalid_hash');
        }

        if ($user->hasVerifiedEmail()) {
            return VerifyEmailResult::alreadyVerified();
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($user->status === 'locked') {
            $this->accountLockoutService->unlock($user);
        }

        return VerifyEmailResult::success();
    }
}
