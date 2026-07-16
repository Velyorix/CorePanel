<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientNote;
use Core\Clients\Notifications\ClientUserInvitationNotification;
use Core\Clients\Services\ClientAuditLogger;
use Core\Clients\Services\ClientImpersonationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * End-to-end smoke coverage for the Étape 8 MR test plan (8.1–8.9).
 */
class ClientManagementAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-acceptance',
            'corepanel.clients.audit.enabled' => true,
            'corepanel.auth.email_verification.required' => false,
            'session.driver' => 'array',
        ]);
    }

    public function test_admin_client_management_happy_path_smoke(): void
    {
        Notification::fake();

        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create([
            'email' => 'owner-smoke@corepanel.test',
            'status' => 'active',
        ]);
        $invitee = User::factory()->withRole('client')->create([
            'email' => 'invitee-smoke@corepanel.test',
        ]);
        $inviteeCore = \Core\Auth\Models\User::query()->whereKey($invitee->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.clients.store'), [
                'user_id' => $owner->id,
                'company_name' => 'Smoke Client Co',
                'country' => 'be',
                'city' => 'Bruxelles',
            ])
            ->assertRedirect();

        $client = Client::query()->where('company_name', 'Smoke Client Co')->firstOrFail();
        $this->assertTrue($client->status === ClientStatus::Active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_CREATED,
            'entity_id' => $client->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.invitations.store', $client), [
                'email' => $invitee->email,
                'role' => ClientMembershipRole::Billing->value,
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $plainToken = null;
        Notification::assertSentTo(
            $inviteeCore,
            ClientUserInvitationNotification::class,
            function (ClientUserInvitationNotification $notification) use (&$plainToken): bool {
                $plainToken = $notification->plainToken;

                return $plainToken !== '';
            },
        );

        $this->assertNotNull($plainToken);

        $this->actingAs($invitee)
            ->get(route('client.invitations.accept', ['token' => $plainToken]))
            ->assertRedirect(route('client.dashboard'));

        $this->assertDatabaseHas('client_users', [
            'client_id' => $client->id,
            'user_id' => $invitee->id,
            'role' => ClientMembershipRole::Billing->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.notes.store', $client), [
                'body' => 'Smoke note for staff context.',
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertDatabaseHas('client_notes', [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'body' => 'Smoke note for staff context.',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.impersonate', $client))
            ->assertRedirect(route('client.dashboard'));

        $this->assertAuthenticatedAs($owner);
        $this->assertTrue(app(ClientImpersonationService::class)->isImpersonating());

        $this->post(route('impersonation.leave'))
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(app(ClientImpersonationService::class)->isImpersonating());

        $this->actingAs($admin)
            ->post(route('admin.clients.suspend', $client))
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertTrue($client->fresh()->status === ClientStatus::Suspended);
        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_STATUS_SUSPENDED,
            'entity_id' => $client->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.clients.destroy', $client))
            ->assertRedirect(route('admin.clients.index'));

        $this->assertSoftDeleted($client);
        $this->assertSame(1, ClientNote::query()->where('client_id', $client->id)->count());
    }
}
