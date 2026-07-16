<?php

namespace Core\Clients\Services;

use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUser;
use Core\Support\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ClientAuditLogger
{
    public const ACTION_CREATED = 'client.created';

    public const ACTION_UPDATED = 'client.updated';

    public const ACTION_DELETED = 'client.deleted';

    public const ACTION_STATUS_SUSPENDED = 'client.status.suspended';

    public const ACTION_STATUS_UNSUSPENDED = 'client.status.unsuspended';

    public const ACTION_STATUS_CLOSED = 'client.status.closed';

    public const ACTION_STATUS_REOPENED = 'client.status.reopened';

    public const ACTION_MEMBER_ADDED = 'client.member.added';

    public const ACTION_MEMBER_UPDATED = 'client.member.updated';

    public const ACTION_MEMBER_REMOVED = 'client.member.removed';

    public const ACTION_INVITATION_CREATED = 'client.invitation.created';

    public const ACTION_INVITATION_ACCEPTED = 'client.invitation.accepted';

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        Client $client,
        ?array $before = null,
        ?array $after = null,
        ?int $actorId = null,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $this->auditLogger->record(
            action: $action,
            actorId: $actorId ?? $this->actorId(),
            entityType: Client::class,
            entityId: $client->id,
            before: $before,
            after: $after,
            ipAddress: $this->ipAddress(),
            userAgent: $this->userAgent(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function clientSnapshot(Client $client): array
    {
        return [
            'user_id' => $client->user_id,
            'company_name' => $client->company_name,
            'vat_number' => $client->vat_number,
            'address' => $client->address,
            'city' => $client->city,
            'country' => $client->country,
            'postal_code' => $client->postal_code,
            'phone' => $client->phone,
            'status' => $client->status instanceof ClientStatus
                ? $client->status->value
                : $client->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function membershipSnapshot(ClientUser $membership): array
    {
        return [
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'role' => $membership->role instanceof ClientMembershipRole
                ? $membership->role->value
                : $membership->role,
            'permissions' => $membership->permissions,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('corepanel.clients.audit.enabled', true);
    }

    private function actorId(): ?int
    {
        $id = Auth::id();

        return $id !== null ? (int) $id : null;
    }

    private function ipAddress(): ?string
    {
        return Request::ip();
    }

    private function userAgent(): ?string
    {
        $userAgent = Request::userAgent();

        return filled($userAgent) ? (string) $userAgent : null;
    }
}
