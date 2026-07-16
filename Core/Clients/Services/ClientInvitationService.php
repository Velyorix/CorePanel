<?php

namespace Core\Clients\Services;

use Core\Auth\Models\User;
use Core\Clients\DataTransferObjects\ClientMembershipData;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUser;
use Core\Clients\Models\ClientUserInvitation;
use Core\Clients\Notifications\ClientUserInvitationNotification;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use RuntimeException;

class ClientInvitationService
{
    public function __construct(
        private readonly ClientInvitationGate $gate,
        private readonly ClientService $clientService,
        private readonly ClientAuditLogger $clientAuditLogger,
    ) {
    }

    /**
     * @return array{0: ClientUserInvitation, 1: string}
     */
    public function createInvitation(
        Client $client,
        string $email,
        ClientMembershipRole $role,
        ?array $permissions,
        User $invitedBy,
    ): array {
        $email = strtolower(trim($email));

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser !== null) {
            $alreadyMember = ClientUser::query()
                ->where('client_id', $client->id)
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($alreadyMember) {
                throw new InvalidArgumentException('This user is already a member of the client.');
            }
        }

        $ttlHours = (int) config('corepanel.auth.client_invitations.invitation_ttl_hours', 72);
        $expiresAt = now()->addHours($ttlHours);

        $pendingInvitation = ClientUserInvitation::query()
            ->where('client_id', $client->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($pendingInvitation !== null) {
            throw new InvalidArgumentException('An invitation for this email already exists.');
        }

        $plainToken = $this->gate->generateToken();
        $hashedToken = $this->gate->hashToken($plainToken);

        $invitation = ClientUserInvitation::query()->create([
            'client_id' => $client->id,
            'email' => $email,
            'token' => $hashedToken,
            'role' => $role->value,
            'permissions' => $permissions,
            'invited_by' => $invitedBy->id,
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'created_at' => now(),
        ]);

        $this->clientAuditLogger->log(
            ClientAuditLogger::ACTION_INVITATION_CREATED,
            $client,
            after: [
                'email' => $email,
                'role' => $role->value,
                'invitation_id' => $invitation->id,
            ],
            actorId: $invitedBy->id,
        );

        $invitation->loadMissing('client');

        if ($existingUser !== null) {
            $existingUser->notify(new ClientUserInvitationNotification($invitation, $plainToken));
        } else {
            Notification::route('mail', $email)->notify(
                new ClientUserInvitationNotification($invitation, $plainToken),
            );
        }

        return [$invitation, $plainToken];
    }

    public function acceptInvitation(string $plainToken, User $user): ClientUser
    {
        $invitation = $this->gate->findValidInvitation($plainToken);

        if ($invitation === null) {
            throw new RuntimeException('This invitation link is invalid or has expired.');
        }

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            throw new InvalidArgumentException('This email address does not match the invitation.');
        }

        $invitation->loadMissing('client');
        $client = $invitation->client;

        if ($client === null) {
            throw new RuntimeException('Invalid invitation target.');
        }

        $role = $invitation->role;
        $permissions = $invitation->permissions;

        $membership = ClientUser::query()
            ->where('client_id', $client->id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership !== null) {
            $membership = $this->clientService->updateMember(
                $membership,
                new ClientMembershipData(
                    userId: $user->id,
                    role: $role,
                    permissions: is_array($permissions) ? $permissions : null,
                ),
            );
        } else {
            $membership = $this->clientService->addMember(
                $client,
                new ClientMembershipData(
                    userId: $user->id,
                    role: $role,
                    permissions: is_array($permissions) ? $permissions : null,
                ),
            );
        }

        $invitation->forceFill(['accepted_at' => now()])->save();

        $this->clientAuditLogger->log(
            ClientAuditLogger::ACTION_INVITATION_ACCEPTED,
            $client,
            after: [
                'email' => $invitation->email,
                'role' => $role->value,
                'invitation_id' => $invitation->id,
            ],
            actorId: $user->id,
        );

        return $membership;
    }
}
