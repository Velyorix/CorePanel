<?php

namespace Core\Clients\Services;

use Core\Auth\Models\User;
use Core\Clients\DataTransferObjects\ClientData;
use Core\Clients\DataTransferObjects\ClientMembershipData;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ClientService
{
    /**
     * @param  array{q?: string|null, status?: ClientStatus|null, sort?: string, dir?: string}  $filters
     * @return LengthAwarePaginator<int, Client>
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['company_name', 'country', 'status', 'created_at'], true)) {
            $sort = 'created_at';
        }

        $query = Client::query()->with('owner');

        if ($status instanceof ClientStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($search, $term): void {
                $builder
                    ->where('company_name', 'like', $term)
                    ->orWhere('country', 'like', $term)
                    ->orWhere('vat_number', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhereHas('owner', function ($ownerQuery) use ($term): void {
                        $ownerQuery
                            ->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(ClientData $data): Client
    {
        $this->assertOwnerExists($data->userId);
        $this->assertCountryFormat($data->country);

        return DB::transaction(function () use ($data): Client {
            $client = Client::query()->create($data->toAttributes());

            if ($data->userId !== null) {
                $this->ensureOwnerMembership($client, $data->userId);
            }

            return $client->fresh(['owner', 'memberships']) ?? $client;
        });
    }

    public function update(Client $client, ClientData $data): Client
    {
        $this->assertOwnerExists($data->userId);
        $this->assertCountryFormat($data->country);

        if ($data->status !== $client->status) {
            throw new RuntimeException('Client status cannot be changed via update. Use a dedicated transition.');
        }

        return DB::transaction(function () use ($client, $data): Client {
            $previousOwnerId = $client->user_id;

            $client->update($data->toAttributes());

            if ($data->userId !== null && $data->userId !== $previousOwnerId) {
                $this->ensureOwnerMembership($client, $data->userId);
            }

            return $client->fresh(['owner', 'memberships']) ?? $client;
        });
    }

    public function suspend(Client $client, ?string $reason = null): Client
    {
        if ($client->status === ClientStatus::Suspended) {
            return $client->fresh(['owner', 'memberships']) ?? $client;
        }

        if ($client->status !== ClientStatus::Active) {
            throw new RuntimeException('Only active clients can be suspended.');
        }

        return $this->transition($client, ClientStatus::Suspended, $reason);
    }

    public function unsuspend(Client $client, ?string $reason = null): Client
    {
        if ($client->status === ClientStatus::Active) {
            return $client->fresh(['owner', 'memberships']) ?? $client;
        }

        if ($client->status !== ClientStatus::Suspended) {
            throw new RuntimeException('Only suspended clients can be unsuspended.');
        }

        return $this->transition($client, ClientStatus::Active, $reason);
    }

    public function close(Client $client, ?string $reason = null): Client
    {
        if ($client->status === ClientStatus::Closed) {
            return $client->fresh(['owner', 'memberships']) ?? $client;
        }

        if (! in_array($client->status, [ClientStatus::Active, ClientStatus::Suspended], true)) {
            throw new RuntimeException('Only active or suspended clients can be closed.');
        }

        return $this->transition($client, ClientStatus::Closed, $reason);
    }

    public function reopen(Client $client, ?string $reason = null): Client
    {
        if ($client->status === ClientStatus::Active) {
            return $client->fresh(['owner', 'memberships']) ?? $client;
        }

        if ($client->status !== ClientStatus::Closed) {
            throw new RuntimeException('Only closed clients can be reopened.');
        }

        return $this->transition($client, ClientStatus::Active, $reason);
    }

    private function transition(Client $client, ClientStatus $target, ?string $reason = null): Client
    {
        if (! $client->status->canTransitionTo($target)) {
            throw new RuntimeException(sprintf(
                'Cannot transition client from %s to %s.',
                $client->status->value,
                $target->value,
            ));
        }

        $client->update([
            'status' => $target,
        ]);

        return $client->fresh(['owner', 'memberships']) ?? $client;
    }

    public function addMember(Client $client, ClientMembershipData $data): ClientUser
    {
        $this->assertOwnerExists($data->userId);

        return DB::transaction(function () use ($client, $data): ClientUser {
            $exists = ClientUser::query()
                ->where('client_id', $client->id)
                ->where('user_id', $data->userId)
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException('This user is already a member of the client.');
            }

            if ($data->role === ClientMembershipRole::Owner) {
                $this->promoteToPrimaryOwner($client, $data->userId);
            }

            return ClientUser::query()->create([
                'client_id' => $client->id,
                'user_id' => $data->userId,
                'role' => $data->role,
                'permissions' => $data->permissions,
                'created_at' => now(),
            ]);
        });
    }

    public function updateMember(ClientUser $membership, ClientMembershipData $data): ClientUser
    {
        if ($membership->user_id !== $data->userId) {
            throw new InvalidArgumentException('Membership user cannot be changed. Remove and re-add the member instead.');
        }

        return DB::transaction(function () use ($membership, $data): ClientUser {
            $client = $membership->client()->firstOrFail();
            $isPrimaryOwner = $client->user_id === $membership->user_id;

            if ($isPrimaryOwner && $data->role !== ClientMembershipRole::Owner) {
                throw new RuntimeException('Reassign ownership before demoting the primary owner.');
            }

            if ($data->role === ClientMembershipRole::Owner) {
                $this->promoteToPrimaryOwner($client, $membership->user_id);
            }

            $membership->update([
                'role' => $data->role,
                'permissions' => $data->permissions,
            ]);

            return $membership->fresh(['user', 'client']) ?? $membership;
        });
    }

    public function removeMember(Client $client, ClientUser $membership): void
    {
        if ($membership->client_id !== $client->id) {
            throw new InvalidArgumentException('Membership does not belong to this client.');
        }

        if ($client->user_id !== null && $client->user_id === $membership->user_id) {
            throw new RuntimeException('Cannot remove the primary owner. Reassign ownership first.');
        }

        $membership->delete();
    }

    private function promoteToPrimaryOwner(Client $client, int $userId): void
    {
        if ($client->user_id !== $userId) {
            $client->update(['user_id' => $userId]);
        }

        ClientUser::query()
            ->where('client_id', $client->id)
            ->where('user_id', '!=', $userId)
            ->where('role', ClientMembershipRole::Owner->value)
            ->update(['role' => ClientMembershipRole::Admin->value]);
    }

    private function ensureOwnerMembership(Client $client, int $userId): void
    {
        $this->promoteToPrimaryOwner($client, $userId);

        ClientUser::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'user_id' => $userId,
            ],
            [
                'role' => ClientMembershipRole::Owner,
                'permissions' => null,
                'created_at' => now(),
            ],
        );
    }

    private function assertOwnerExists(?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        if (! User::query()->whereKey($userId)->exists()) {
            throw new InvalidArgumentException('The selected owner user does not exist.');
        }
    }

    private function assertCountryFormat(?string $country): void
    {
        if ($country === null) {
            return;
        }

        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            throw new InvalidArgumentException('The country must be a 2-letter ISO code.');
        }
    }
}
