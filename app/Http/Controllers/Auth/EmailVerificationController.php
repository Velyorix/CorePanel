<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Core\Auth\Actions\SendEmailVerificationNotificationAction;
use Core\Auth\Actions\VerifyEmailAction;
use Core\Auth\DataTransferObjects\SendEmailVerificationResult;
use Core\Auth\Services\EmailVerificationGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly EmailVerificationGate $emailVerificationGate,
    ) {
    }

    public function notice(Request $request): View|RedirectResponse
    {
        if (! $this->emailVerificationGate->isRequired()) {
            return redirect()->intended('/');
        }

        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->intended('/');
        }

        return view('auth.verify-email');
    }

    public function verify(
        Request $request,
        int|string $id,
        string $hash,
        VerifyEmailAction $verifyEmailAction,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('You must be signed in to verify your email address.'),
                ]);
        }

        $result = $verifyEmailAction->execute($user, $id, $hash);

        if (! $result->successful) {
            return redirect()
                ->route('verification.notice')
                ->withErrors([
                    'email' => __('This verification link is invalid or has expired.'),
                ]);
        }

        return redirect()
            ->intended('/')
            ->with('status', __('Your email address has been verified.'));
    }

    public function send(
        Request $request,
        SendEmailVerificationNotificationAction $sendEmailVerificationNotificationAction,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null || $user->hasVerifiedEmail()) {
            return redirect()->intended('/');
        }

        $result = $sendEmailVerificationNotificationAction->execute($user, $request->ip());

        if (! $result->sent) {
            return back()->withErrors([
                'email' => $this->resendFailureMessage($result),
            ]);
        }

        return back()->with('status', __('A new verification link has been sent to your email address.'));
    }

    private function resendFailureMessage(SendEmailVerificationResult $result): string
    {
        if (str_starts_with((string) $result->failureReason, 'rate_limited:')) {
            $seconds = (int) substr((string) $result->failureReason, strlen('rate_limited:'));

            return __('Too many verification emails sent. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]);
        }

        return __('Unable to send a verification email. Please try again.');
    }
}
