<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Core\Auth\Actions\RegisterAction;
use Core\Auth\DataTransferObjects\RegisterResult;
use Core\Auth\Services\RegistrationGate;
use Core\Auth\Services\UserSessionTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        private readonly RegistrationGate $registrationGate,
        private readonly UserSessionTracker $userSessionTracker,
    ) {
    }

    /**
     * Show the open registration form.
     */
    public function create(): View
    {
        return view('auth.register', [
            'invitation' => null,
            'invitationToken' => null,
        ]);
    }

    /**
     * Handle open registration.
     */
    public function store(RegisterRequest $request, RegisterAction $registerAction): RedirectResponse
    {
        return $this->completeRegistration(
            $request,
            $registerAction->execute($request->registerData()),
        );
    }

    /**
     * Show the invitation registration form.
     */
    public function createFromInvitation(string $token): View|RedirectResponse
    {
        $invitation = $this->registrationGate->findValidInvitation($token);

        if ($invitation === null) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('This invitation link is invalid or has expired.'),
                ]);
        }

        return view('auth.register', [
            'invitation' => $invitation,
            'invitationToken' => $token,
        ]);
    }

    /**
     * Handle invitation registration.
     */
    public function storeFromInvitation(
        string $token,
        RegisterRequest $request,
        RegisterAction $registerAction,
    ): RedirectResponse {
        $invitation = $this->registrationGate->findValidInvitation($token);

        if ($invitation === null) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('This invitation link is invalid or has expired.'),
                ]);
        }

        if (strcasecmp($invitation->email, $request->input('email')) !== 0) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => __('This email address does not match the invitation.'),
                ]);
        }

        return $this->completeRegistration(
            $request,
            $registerAction->execute($request->registerData(), $invitation),
        );
    }

    private function completeRegistration(RegisterRequest $request, RegisterResult $result): RedirectResponse
    {
        if (! $result->successful) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => $this->failureMessage($result->failureReason),
                ]);
        }

        Auth::login($result->user);

        $this->userSessionTracker->record(
            $result->user,
            $request->session()->getId(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->intended('/');
    }

    private function failureMessage(?string $reason): string
    {
        return match ($reason) {
            'email_taken' => __('An account with this email address already exists.'),
            'invitation_required' => __('Registration requires a valid invitation.'),
            'invitation_email_mismatch' => __('This email address does not match the invitation.'),
            'role_unavailable' => __('Registration is temporarily unavailable. Please contact support.'),
            default => __('Unable to complete registration. Please try again.'),
        };
    }
}
