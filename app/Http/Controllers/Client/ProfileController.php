<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateClientPasswordRequest;
use App\Http\Requests\Client\UpdateClientProfileRequest;
use App\Models\User;
use Core\Auth\Actions\SendEmailVerificationNotificationAction;
use Core\Auth\Services\EmailVerificationGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly EmailVerificationGate $emailVerificationGate,
        private readonly SendEmailVerificationNotificationAction $sendEmailVerificationNotificationAction,
    ) {
    }

    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user?->can('client.account.view') ?? false, 403);

        return view('client.profile.edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateClientProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();
        $emailChanged = strcasecmp($user->email, $validated['email']) !== 0;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($emailChanged && $this->emailVerificationGate->isRequired()) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged && $this->emailVerificationGate->isRequired()) {
            $this->sendEmailVerificationNotificationAction->execute($user, $request->ip());

            return redirect()
                ->route('client.profile.edit')
                ->with('status', __('Profile updated. Please verify your new email address.'));
        }

        return redirect()
            ->route('client.profile.edit')
            ->with('status', __('Profile updated successfully.'));
    }

    public function updatePassword(UpdateClientPasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return redirect()
            ->route('client.profile.edit')
            ->with('password_status', __('Password updated successfully.'));
    }
}

