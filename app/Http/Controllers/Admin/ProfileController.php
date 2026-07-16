<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
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

        return view('admin.profile.edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateAdminProfileRequest $request): RedirectResponse
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
                ->route('admin.profile.edit')
                ->with('status', __('Profile updated. Please verify your new email address.'));
        }

        return redirect()
            ->route('admin.profile.edit')
            ->with('status', __('Profile updated successfully.'));
    }

    public function updatePassword(UpdateAdminPasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return redirect()
            ->route('admin.profile.edit')
            ->with('password_status', __('Password updated successfully.'));
    }
}
