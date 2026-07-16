<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Core\Clients\DataTransferObjects\ClientData;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUser;
use Core\Clients\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ClientServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClientService $clientService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientService = app(ClientService::class);
    }

    public function test_client_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ClientService::class),
            app(ClientService::class),
        );
    }

    public function test_create_persists_client_and_owner_membership(): void
    {
        $owner = User::factory()->create();

        $client = $this->clientService->create(ClientData::fromArray([
            'user_id' => $owner->id,
            'company_name' => 'Acme Hosting',
            'vat_number' => 'BE0123456789',
            'address' => '1 Rue Example',
            'city' => 'Bruxelles',
            'country' => 'be',
            'postal_code' => '1000',
            'phone' => '+32000000000',
        ]));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'user_id' => $owner->id,
            'company_name' => 'Acme Hosting',
            'country' => 'BE',
            'status' => ClientStatus::Active->value,
        ]);

        $this->assertTrue($client->status === ClientStatus::Active);
        $this->assertTrue($client->relationLoaded('owner'));
        $this->assertTrue($client->relationLoaded('memberships'));

        $this->assertDatabaseHas('client_users', [
            'client_id' => $client->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_create_without_owner_skips_membership(): void
    {
        $client = $this->clientService->create(ClientData::fromArray([
            'company_name' => 'Orphan Co',
            'country' => 'FR',
        ]));

        $this->assertNull($client->user_id);
        $this->assertDatabaseCount('client_users', 0);
    }

    public function test_create_rejects_unknown_owner(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected owner user does not exist.');

        $this->clientService->create(ClientData::fromArray([
            'user_id' => 999999,
            'company_name' => 'Broken Co',
        ]));
    }

    public function test_create_rejects_invalid_country(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The country must be a 2-letter ISO code.');

        $this->clientService->create(ClientData::fromArray([
            'company_name' => 'Broken Co',
            'country' => 'BEL',
        ]));
    }

    public function test_update_changes_attributes_and_owner_membership(): void
    {
        $previousOwner = User::factory()->create();
        $newOwner = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $previousOwner->id,
            'company_name' => 'Old Name',
            'country' => 'FR',
            'status' => ClientStatus::Active,
        ]);

        ClientUser::query()->create([
            'client_id' => $client->id,
            'user_id' => $previousOwner->id,
            'role' => 'owner',
            'created_at' => now(),
        ]);

        $updated = $this->clientService->update($client, ClientData::fromArray([
            'user_id' => $newOwner->id,
            'company_name' => 'New Name',
            'country' => 'de',
            'city' => 'Berlin',
            'status' => ClientStatus::Active->value,
        ]));

        $this->assertSame('New Name', $updated->company_name);
        $this->assertSame('DE', $updated->country);
        $this->assertSame('Berlin', $updated->city);
        $this->assertSame($newOwner->id, $updated->user_id);
        $this->assertTrue($updated->status === ClientStatus::Active);

        $this->assertDatabaseHas('client_users', [
            'client_id' => $client->id,
            'user_id' => $newOwner->id,
            'role' => 'owner',
        ]);
    }

    public function test_update_rejects_status_change(): void
    {
        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Client status cannot be changed via update. Use a dedicated transition.');

        $this->clientService->update($client, ClientData::fromArray([
            'company_name' => $client->company_name,
            'status' => ClientStatus::Suspended->value,
        ]));
    }

    public function test_suspend_sets_suspended_status(): void
    {
        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
        ]);

        $suspended = $this->clientService->suspend($client, 'Non-payment');

        $this->assertTrue($suspended->status === ClientStatus::Suspended);
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'status' => ClientStatus::Suspended->value,
        ]);
    }

    public function test_suspend_is_idempotent_when_already_suspended(): void
    {
        $client = Client::factory()->create([
            'status' => ClientStatus::Suspended,
        ]);

        $suspended = $this->clientService->suspend($client);

        $this->assertTrue($suspended->status === ClientStatus::Suspended);
        $this->assertSame($client->id, $suspended->id);
    }

    public function test_suspend_rejects_closed_client(): void
    {
        $client = Client::factory()->create([
            'status' => ClientStatus::Closed,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only active clients can be suspended.');

        $this->clientService->suspend($client);
    }

    public function test_unsuspend_reactivates_suspended_client(): void
    {
        $client = Client::factory()->create([
            'status' => ClientStatus::Suspended,
        ]);

        $reactivated = $this->clientService->unsuspend($client);

        $this->assertTrue($reactivated->status === ClientStatus::Active);
    }

    public function test_unsuspend_rejects_closed_client(): void
    {
        $client = Client::factory()->create([
            'status' => ClientStatus::Closed,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only suspended clients can be unsuspended.');

        $this->clientService->unsuspend($client);
    }

    public function test_close_and_reopen_lifecycle(): void
    {
        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
        ]);

        $closed = $this->clientService->close($client, 'Contract ended');
        $this->assertTrue($closed->status === ClientStatus::Closed);

        $reopened = $this->clientService->reopen($closed);
        $this->assertTrue($reopened->status === ClientStatus::Active);
    }

    public function test_close_from_suspended_is_allowed(): void
    {
        $client = Client::factory()->create([
            'status' => ClientStatus::Suspended,
        ]);

        $closed = $this->clientService->close($client);

        $this->assertTrue($closed->status === ClientStatus::Closed);
    }
}
