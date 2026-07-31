<?php

namespace Core\API\Services;

use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Permissions\Services\ResourceOwnershipResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolve which clients an API user can access.
 */
class ApiClientAccessService
{
    public function __construct(
        private readonly ResourceOwnershipResolver $ownership,
    ) {
    }

    /**
     * @return list<int>
     */
    public function accessibleClientIds(User $user): array
    {
        $memberIds = $user->clients()->pluck('clients.id')->all();
        $ownedIds = $user->ownedClients()->pluck('id')->all();

        return array_values(array_unique(array_map('intval', [...$memberIds, ...$ownedIds])));
    }

    public function accessibleClientsQuery(User $user)
    {
        $ids = $this->accessibleClientIds($user);

        return Client::query()
            ->whereIn('id', $ids === [] ? [0] : $ids)
            ->orderBy('id');
    }

    public function canAccessClient(User $user, Client $client): bool
    {
        return $this->ownership->userOwns($user, $client);
    }

    public function assertCanAccessClient(User $user, Client $client): void
    {
        if (! $this->canAccessClient($user, $client)) {
            abort(404, __('Resource not found.'));
        }
    }

    public function assertCanAccessOwnedResource(User $user, Model $resource): void
    {
        $clientId = (int) ($resource->getAttribute('client_id') ?? 0);

        if ($clientId <= 0 || ! in_array($clientId, $this->accessibleClientIds($user), true)) {
            abort(404, __('Resource not found.'));
        }
    }

    public function primaryClient(User $user): ?Client
    {
        return $this->accessibleClientsQuery($user)->first();
    }
}
