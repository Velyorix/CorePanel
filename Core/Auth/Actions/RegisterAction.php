<?php

namespace Core\Auth\Actions;

use Core\Auth\DataTransferObjects\RegisterData;
use Core\Auth\DataTransferObjects\RegisterResult;
use Core\Auth\Models\User;
use Core\Auth\Models\UserInvitation;
use Core\Auth\Services\RegistrationGate;
use Core\Permissions\Models\Role;
use Illuminate\Support\Facades\DB;

class RegisterAction
{
    public function __construct(
        private readonly RegistrationGate $registrationGate,
    ) {
    }

    public function execute(RegisterData $data, ?UserInvitation $invitation = null): RegisterResult
    {
        if ($this->registrationGate->isInviteOnly() && $invitation === null) {
            return RegisterResult::failed('invitation_required');
        }

        if ($invitation !== null) {
            if (! $invitation->isPending()) {
                return RegisterResult::failed('invitation_invalid');
            }

            if (strcasecmp($invitation->email, $data->email) !== 0) {
                return RegisterResult::failed('invitation_email_mismatch');
            }
        }

        if (User::query()->where('email', $data->email)->exists()) {
            return RegisterResult::failed('email_taken');
        }

        $role = $this->resolveRole($invitation);

        if ($role === null) {
            return RegisterResult::failed('role_unavailable');
        }

        $user = DB::transaction(function () use ($data, $invitation, $role): User {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'status' => 'active',
            ]);

            $user->roles()->sync([$role->id]);

            if ($invitation !== null) {
                $invitation->forceFill(['accepted_at' => now()])->save();
            }

            return $user;
        });

        return RegisterResult::success($user);
    }

    private function resolveRole(?UserInvitation $invitation): ?Role
    {
        if ($invitation?->role_id !== null) {
            return Role::query()->find($invitation->role_id);
        }

        return Role::query()
            ->where('name', $this->registrationGate->defaultRoleName())
            ->first();
    }
}
