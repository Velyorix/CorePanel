<?php

namespace Core\Clients\Services;

use Core\Clients\Models\ClientUserInvitation;
use Illuminate\Support\Str;

class ClientInvitationGate
{
    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function generateToken(): string
    {
        return Str::random(64);
    }

    public function findValidInvitation(string $plainToken): ?ClientUserInvitation
    {
        $invitation = ClientUserInvitation::query()
            ->where('token', $this->hashToken($plainToken))
            ->first();

        if ($invitation === null || ! $invitation->isPending()) {
            return null;
        }

        return $invitation;
    }
}

