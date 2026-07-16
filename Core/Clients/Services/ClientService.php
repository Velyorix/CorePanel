<?php

namespace Core\Clients\Services;

use Core\Auth\Models\User;
use Core\Clients\DataTransferObjects\ClientData;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUser;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ClientService
{
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

        if ($client->status === ClientStatus::Closed && $data->status !== ClientStatus::Closed) {
            throw new RuntimeException('Closed clients cannot be reopened via update. Use a dedicated transition.');
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
        if ($client->status === ClientStatus::Closed) {
            throw new RuntimeException('Closed clients cannot be suspended.');
        }

        if ($client->status === ClientStatus::Suspended) {
            return $client->fresh(['owner', 'memberships']) ?? $client;
        }

        $client->update([
            'status' => ClientStatus::Suspended,
        ]);

        return $client->fresh(['owner', 'memberships']) ?? $client;
    }

    private function ensureOwnerMembership(Client $client, int $userId): void
    {
        ClientUser::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'user_id' => $userId,
            ],
            [
                'role' => 'owner',
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
