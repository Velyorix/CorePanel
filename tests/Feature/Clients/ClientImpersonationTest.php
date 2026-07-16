<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientAuditLogger;
use Core\Clients\Services\ClientImpersonationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-impersonation',
            'corepanel.clients.audit.enabled' => true,
            'corepanel.auth.email_verification.required' => false,
            'session.driver' => 'array',
        ]);
    }

    public function test_admin_can_impersonate_client_owner_and_leave(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create([
            'email' => 'owner-impersonate@corepanel.test',
            'status' => 'active',
        ]);
        $client = Client::factory()->create([
            'user_id' => $owner->id,
            'company_name' => 'Impersonate Co',
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.impersonate', $client))
            ->assertRedirect(route('client.dashboard'));

        $this->assertAuthenticatedAs($owner);
        $this->assertTrue(app(ClientImpersonationService::class)->isImpersonating());

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_IMPERSONATION_STARTED,
            'entity_id' => $client->id,
            'actor_id' => $admin->id,
        ]);

        $this->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee(__('Leave impersonation'), false)
            ->assertSee('owner-impersonate@corepanel.test', false);

        $this->post(route('impersonation.leave'))
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(app(ClientImpersonationService::class)->isImpersonating());

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_IMPERSONATION_STOPPED,
            'entity_id' => $client->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_support_cannot_impersonate_clients(): void
    {
        $support = User::factory()->withRole('support')->create();
        $owner = User::factory()->withRole('client')->create();
        $client = Client::factory()->create([
            'user_id' => $owner->id,
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($support)
            ->post(route('admin.clients.impersonate', $client))
            ->assertForbidden();
    }

    public function test_impersonation_requires_primary_owner(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create([
            'user_id' => null,
            'company_name' => 'Orphan Co',
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.clients.show', $client))
            ->post(route('admin.clients.impersonate', $client))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHasErrors('impersonation');
    }

    public function test_cannot_impersonate_inactive_owner(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create([
            'status' => 'suspended',
        ]);
        $client = Client::factory()->create([
            'user_id' => $owner->id,
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.clients.show', $client))
            ->post(route('admin.clients.impersonate', $client))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHasErrors('impersonation');
    }

    public function test_leave_without_impersonation_redirects_with_error(): void
    {
        $clientUser = User::factory()->withRole('client')->create();

        $this->actingAs($clientUser)
            ->post(route('impersonation.leave'))
            ->assertRedirect(route('client.dashboard'))
            ->assertSessionHasErrors('impersonation');
    }
}
