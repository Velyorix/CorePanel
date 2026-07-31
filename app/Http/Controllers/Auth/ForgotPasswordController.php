<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Core\Auth\Actions\ResetPasswordAction;
use Core\Auth\Actions\SendPasswordResetLinkAction;
use Core\Auth\DataTransferObjects\PasswordResetLinkResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(
        ForgotPasswordRequest $request,
        SendPasswordResetLinkAction $sendPasswordResetLinkAction,
    ): RedirectResponse {
        $result = $sendPasswordResetLinkAction->execute(
            $request->string('email')->toString(),
            $request->ip(),
        );

        if (! $result->successful) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $this->failureMessage($result),
                ]);
        }

        return back()->with('status', __('If an account exists for that email, a password reset link has been sent.'));
    }

    private function failureMessage(PasswordResetLinkResult $result): string
    {
        if (str_starts_with((string) $result->failureReason, 'rate_limited:')) {
            $seconds = (int) substr((string) $result->failureReason, strlen('rate_limited:'));

            return __('Too many password reset attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]);
        }

        return match ($result->failureReason) {
            'throttled' => __('Please wait before retrying.'),
            default => __('Unable to send a password reset link. Please try again.'),
        };
    }
}
