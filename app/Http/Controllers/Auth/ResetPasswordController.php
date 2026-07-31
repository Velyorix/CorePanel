<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Core\Auth\Actions\ResetPasswordAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(
        ResetPasswordRequest $request,
        ResetPasswordAction $resetPasswordAction,
    ): RedirectResponse {
        $result = $resetPasswordAction->execute(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('token')->toString(),
            $request->ip(),
        );

        if (! $result->successful) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $this->failureMessage($result->failureReason),
                ]);
        }

        return redirect()
            ->route('login')
            ->with('status', __('Your password has been reset. You can now sign in.'));
    }

    private function failureMessage(?string $reason): string
    {
        return match ($reason) {
            'invalid_token' => __('This password reset link is invalid or has expired.'),
            'invalid_user' => __('We could not find a user with that email address.'),
            default => __('Unable to reset password. Please try again.'),
        };
    }
}
