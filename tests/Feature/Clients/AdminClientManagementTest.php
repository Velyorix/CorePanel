<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-clients',
        ]);
    }

    public function test_admin_can_view_clients_index(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create([
            'company_name' => 'Acme Hosting',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Acme Hosting')
            ->assertSee((string) $client->id);
    }

    public function test_support_can_view_but_cannot_create_clients(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.clients.index'))
            ->assertOk();

        $this->actingAs($support)
            ->get(route('admin.clients.create'))
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('admin.clients.store'), [
                'company_name' => 'Blocked Co',
                'status' => ClientStatus::Active->value,
            ])
            ->assertForbidden();
    }

    public function test_client_role_cannot_access_admin_clients(): void
    {
        $clientUser = User::factory()->withRole('client')->create();

        $this->actingAs($clientUser)
            ->get(route('admin.clients.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_clients(): void
    {
        $this->get(route('admin.clients.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_create_update_and_delete_client(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.clients.store'), [
                'user_id' => $owner->id,
                'company_name' => 'Nova Cloud',
                'vat_number' => 'BE0123456789',
                'address' => '10 Rue Test',
                'city' => 'Bruxelles',
                'country' => 'be',
                'postal_code' => '1000',
                'phone' => '+32000000000',
            ])
            ->assertRedirect();

        $client = Client::query()->where('company_name', 'Nova Cloud')->firstOrFail();

        $this->assertSame($owner->id, $client->user_id);
        $this->assertSame('BE', $client->country);
        $this->assertTrue($client->status === ClientStatus::Active);
        $this->assertDatabaseHas('client_users', [
            'client_id' => $client->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('Nova Cloud')
            ->assertSee($owner->email);

        $newOwner = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.clients.update', $client), [
                'user_id' => $newOwner->id,
                'company_name' => 'Nova Cloud Updated',
                'country' => 'FR',
                'city' => 'Paris',
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $client->refresh();

        $this->assertSame('Nova Cloud Updated', $client->company_name);
        $this->assertSame($newOwner->id, $client->user_id);
        $this->assertSame('FR', $client->country);
        $this->assertTrue($client->status === ClientStatus::Active);
        $this->assertDatabaseHas('client_users', [
            'client_id' => $client->id,
            'user_id' => $newOwner->id,
            'role' => 'owner',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.clients.destroy', $client))
            ->assertRedirect(route('admin.clients.index'));

        $this->assertSoftDeleted($client);
    }

    public function test_admin_cannot_change_status_via_update_payload(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create([
            'status' => ClientStatus::Closed,
            'company_name' => 'Closed Co',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.clients.update', $client), [
                'company_name' => 'Closed Co',
                'status' => ClientStatus::Active->value,
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertTrue($client->fresh()->status === ClientStatus::Closed);
    }

    public function test_admin_can_run_status_lifecycle_transitions(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
            'company_name' => 'Lifecycle Co',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.suspend', $client))
            ->assertRedirect(route('admin.clients.show', $client));
        $this->assertTrue($client->fresh()->status === ClientStatus::Suspended);

        $this->actingAs($admin)
            ->post(route('admin.clients.unsuspend', $client))
            ->assertRedirect(route('admin.clients.show', $client));
        $this->assertTrue($client->fresh()->status === ClientStatus::Active);

        $this->actingAs($admin)
            ->post(route('admin.clients.close', $client))
            ->assertRedirect(route('admin.clients.show', $client));
        $this->assertTrue($client->fresh()->status === ClientStatus::Closed);

        $this->actingAs($admin)
            ->post(route('admin.clients.reopen', $client))
            ->assertRedirect(route('admin.clients.show', $client));
        $this->assertTrue($client->fresh()->status === ClientStatus::Active);
    }

    public function test_support_cannot_transition_client_status(): void
    {
        $support = User::factory()->withRole('support')->create();
        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($support)
            ->post(route('admin.clients.suspend', $client))
            ->assertForbidden();
    }

    public function test_create_form_validation_rejects_invalid_country(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->from(route('admin.clients.create'))
            ->post(route('admin.clients.store'), [
                'company_name' => 'Bad Country Co',
                'country' => 'BEL',
            ])
            ->assertRedirect(route('admin.clients.create'))
            ->assertSessionHasErrors('country');
    }

    public function test_invalid_status_transition_returns_error(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create([
            'status' => ClientStatus::Closed,
            'company_name' => 'Closed Status Co',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.clients.show', $client))
            ->post(route('admin.clients.suspend', $client))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHasErrors('status');

        $this->assertTrue($client->fresh()->status === ClientStatus::Closed);
    }
}
