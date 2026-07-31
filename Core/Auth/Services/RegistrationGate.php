<?php

namespace Core\Auth\Services;

use Core\Auth\Models\UserInvitation;
use Illuminate\Support\Str;

class RegistrationGate
{
    public function mode(): string
    {
        return (string) config('corepanel.auth.registration.mode', 'invite');
    }

    public function isOpen(): bool
    {
        return $this->mode() === 'open';
    }

    public function isInviteOnly(): bool
    {
        return $this->mode() === 'invite';
    }

    public function defaultRoleName(): string
    {
        return (string) config('corepanel.auth.registration.default_role', 'client');
    }

    public function invitationTtlHours(): int
    {
        return (int) config('corepanel.auth.registration.invitation_ttl_hours', 72);
    }

    public function findValidInvitation(string $plainToken): ?UserInvitation
    {
        $invitation = UserInvitation::query()
            ->where('token', $this->hashToken($plainToken))
            ->first();

        if ($invitation === null || ! $invitation->isPending()) {
            return null;
        }

        return $invitation;
    }

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function generateToken(): string
    {
        return Str::random(64);
    }
}
