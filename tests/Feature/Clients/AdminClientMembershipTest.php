<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Core\Clients\DataTransferObjects\ClientMembershipData;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class AdminClientMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-client-members',
        ]);
    }

    public function test_admin_can_add_update_and_remove_member(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $client = app(ClientService::class)->create(
            \Core\Clients\DataTransferObjects\ClientData::fromArray([
                'user_id' => $owner->id,
                'company_name' => 'Multi User Co',
                'status' => ClientStatus::Active->value,
            ]),
        );

        $this->actingAs($admin)
            ->post(route('admin.clients.members.store', $client), [
                'user_id' => $member->id,
                'role' => ClientMembershipRole::Billing->value,
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $membership = $client->memberships()->where('user_id', $member->id)->firstOrFail();
        $this->assertTrue($membership->role === ClientMembershipRole::Billing);

        $this->actingAs($admin)
            ->put(route('admin.clients.members.update', [$client, $membership]), [
                'role' => ClientMembershipRole::Manager->value,
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertTrue($membership->fresh()->role === ClientMembershipRole::Manager);

        $this->actingAs($admin)
            ->delete(route('admin.clients.members.destroy', [$client, $membership]))
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertDatabaseMissing('client_users', [
            'id' => $membership->id,
        ]);
    }

    public function test_admin_cannot_remove_primary_owner_membership(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->create();

        $client = app(ClientService::class)->create(
            \Core\Clients\DataTransferObjects\ClientData::fromArray([
                'user_id' => $owner->id,
                'company_name' => 'Owner Locked Co',
                'status' => ClientStatus::Active->value,
            ]),
        );

        $ownerMembership = $client->memberships()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.clients.show', $client))
            ->delete(route('admin.clients.members.destroy', [$client, $ownerMembership]))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHasErrors('membership');

        $this->assertDatabaseHas('client_users', [
            'id' => $ownerMembership->id,
            'role' => ClientMembershipRole::Owner->value,
        ]);
    }

    public function test_promoting_member_to_owner_demotes_previous_owner(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $service = app(ClientService::class);

        $client = $service->create(
            \Core\Clients\DataTransferObjects\ClientData::fromArray([
                'user_id' => $owner->id,
                'company_name' => 'Promote Co',
                'status' => ClientStatus::Active->value,
            ]),
        );

        $membership = $service->addMember($client, ClientMembershipData::fromArray([
            'user_id' => $member->id,
            'role' => ClientMembershipRole::Admin->value,
        ]));

        $this->actingAs($admin)
            ->put(route('admin.clients.members.update', [$client, $membership]), [
                'role' => ClientMembershipRole::Owner->value,
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $client->refresh();

        $this->assertSame($member->id, $client->user_id);
        $this->assertTrue($membership->fresh()->role === ClientMembershipRole::Owner);
        $this->assertTrue(
            $client->memberships()->where('user_id', $owner->id)->firstOrFail()->role
                === ClientMembershipRole::Admin,
        );
    }

    public function test_support_cannot_manage_members(): void
    {
        $support = User::factory()->withRole('support')->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $client = app(ClientService::class)->create(
            \Core\Clients\DataTransferObjects\ClientData::fromArray([
                'user_id' => $owner->id,
                'company_name' => 'Support Read Co',
                'status' => ClientStatus::Active->value,
            ]),
        );

        $this->actingAs($support)
            ->post(route('admin.clients.members.store', $client), [
                'user_id' => $member->id,
                'role' => ClientMembershipRole::User->value,
            ])
            ->assertForbidden();
    }

    public function test_service_rejects_duplicate_membership(): void
    {
        $owner = User::factory()->create();
        $service = app(ClientService::class);

        $client = $service->create(
            \Core\Clients\DataTransferObjects\ClientData::fromArray([
                'user_id' => $owner->id,
                'company_name' => 'Dup Co',
                'status' => ClientStatus::Active->value,
            ]),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This user is already a member of the client.');

        $service->addMember($client, ClientMembershipData::fromArray([
            'user_id' => $owner->id,
            'role' => ClientMembershipRole::User->value,
        ]));
    }

    public function test_service_rejects_demoting_primary_owner(): void
    {
        $owner = User::factory()->create();
        $service = app(ClientService::class);

        $client = $service->create(
            \Core\Clients\DataTransferObjects\ClientData::fromArray([
                'user_id' => $owner->id,
                'company_name' => 'Demote Co',
                'status' => ClientStatus::Active->value,
            ]),
        );

        $ownerMembership = $client->memberships()->where('user_id', $owner->id)->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reassign ownership before demoting the primary owner.');

        $service->updateMember($ownerMembership, ClientMembershipData::fromArray([
            'user_id' => $owner->id,
            'role' => ClientMembershipRole::Admin->value,
        ]));
    }

    public function test_cross_client_membership_route_returns_not_found(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $member = User::factory()->create();

        $clientA = app(ClientService::class)->create(
            \Core\Clients\DataTransferObjects\ClientData::fromArray([
                'user_id' => $ownerA->id,
                'company_name' => 'Client A',
                'status' => ClientStatus::Active->value,
            ]),
        );

        $clientB = app(ClientService::class)->create(
            \Core\Clients\DataTransferObjects\ClientData::fromArray([
                'user_id' => $ownerB->id,
                'company_name' => 'Client B',
                'status' => ClientStatus::Active->value,
            ]),
        );

        $membershipOnB = app(ClientService::class)->addMember(
            $clientB,
            new ClientMembershipData(
                userId: $member->id,
                role: ClientMembershipRole::User,
                permissions: null,
            ),
        );

        $this->actingAs($admin)
            ->delete(route('admin.clients.members.destroy', [$clientA, $membershipOnB]))
            ->assertNotFound();

        $this->assertDatabaseHas('client_users', ['id' => $membershipOnB->id]);
    }
}
