<?php

namespace Core\Auth\Actions;

use Core\Auth\DataTransferObjects\LoginCredentials;
use Core\Auth\DataTransferObjects\LoginResult;
use Core\Auth\Models\User;
use Illuminate\Support\Facades\Auth;

class LoginAction
{
    public function execute(LoginCredentials $credentials): LoginResult
    {
        $user = User::query()->where('email', $credentials->email)->first();

        if ($user === null) {
            return LoginResult::failed('invalid_credentials');
        }

        if ($user->status !== 'active') {
            return LoginResult::failed('account_inactive', $user);
        }

        if (! Auth::attempt(
            [
                'email' => $credentials->email,
                'password' => $credentials->password,
            ],
            $credentials->remember,
        )) {
            return LoginResult::failed('invalid_credentials', $user);
        }

        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::user();
        $authenticatedUser->forceFill(['last_login_at' => now()])->save();

        request()->session()->regenerate();

        return LoginResult::success($authenticatedUser);
    }
}
