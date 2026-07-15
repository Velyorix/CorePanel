<?php

namespace Core\Auth\Actions;

use Core\Auth\DataTransferObjects\LoginCredentials;
use Core\Auth\DataTransferObjects\LoginResult;
use Core\Auth\Models\User;
use Core\Auth\Services\LoginRateLimiter;
use Illuminate\Support\Facades\Auth;

class LoginAction
{
    public function __construct(
        private readonly LoginRateLimiter $loginRateLimiter,
    ) {
    }

    public function execute(LoginCredentials $credentials, string $ipAddress): LoginResult
    {
        $user = User::query()->where('email', $credentials->email)->first();

        if ($user === null) {
            $this->loginRateLimiter->hit($credentials->email, $ipAddress);

            return LoginResult::failed('invalid_credentials');
        }

        if ($user->status !== 'active') {
            $this->loginRateLimiter->hit($credentials->email, $ipAddress);

            return LoginResult::failed('account_inactive', $user);
        }

        if (! Auth::attempt(
            [
                'email' => $credentials->email,
                'password' => $credentials->password,
            ],
            $credentials->remember,
        )) {
            $this->loginRateLimiter->hit($credentials->email, $ipAddress);

            return LoginResult::failed('invalid_credentials', $user);
        }

        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::user();
        $authenticatedUser->forceFill(['last_login_at' => now()])->save();

        $this->loginRateLimiter->clear($credentials->email, $ipAddress);

        request()->session()->regenerate();

        return LoginResult::success($authenticatedUser);
    }
}
