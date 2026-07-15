<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Core\Auth\Actions\LoginAction;
use Core\Auth\Services\EmailVerificationGate;
use Core\Auth\Services\UserSessionTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly UserSessionTracker $userSessionTracker,
        private readonly EmailVerificationGate $emailVerificationGate,
    ) {
    }
    /**
     * Show the login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle a login request.
     */
    public function store(LoginRequest $request, LoginAction $loginAction): RedirectResponse
    {
        $result = $loginAction->execute($request->credentials(), $request->ip());

        if (! $result->successful) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => $this->failureMessage($result->failureReason),
                ]);
        }

        $this->userSessionTracker->record(
            $result->user,
            $request->session()->getId(),
            $request->ip(),
            $request->userAgent(),
        );

        if ($this->emailVerificationGate->isRequired() && ! $result->user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended('/');
    }

    /**
     * Log the user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->hasSession()) {
            $this->userSessionTracker->revoke(
                $request->session()->getId(),
                $user?->id,
                $request->ip(),
                $request->userAgent(),
            );
        }

        if ($user !== null) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function failureMessage(?string $reason): string
    {
        return match ($reason) {
            'account_inactive' => __('This account is not active. Please contact support.'),
            'account_locked' => __('This account has been locked due to too many failed login attempts. Reset your password or contact support.'),
            default => __('These credentials do not match our records.'),
        };
    }
}
