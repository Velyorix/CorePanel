<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Core\Clients\DataTransferObjects\ClientData;
use Core\Clients\DataTransferObjects\ClientMembershipData;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientAuditLogger;
use Core\Clients\Services\ClientInvitationService;
use Core\Clients\Services\ClientService;
use Core\Support\Models\AuditLog;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private ClientService $clientService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-audit',
            'corepanel.clients.audit.enabled' => true,
        ]);

        $this->clientService = app(ClientService::class);
    }

    public function test_create_update_delete_and_status_transitions_are_audited(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->create();

        $this->actingAs($admin);

        $client = $this->clientService->create(ClientData::fromArray([
            'user_id' => $owner->id,
            'company_name' => 'Audit Co',
            'country' => 'BE',
            'status' => ClientStatus::Active->value,
        ]));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => ClientAuditLogger::ACTION_CREATED,
            'entity_type' => Client::class,
            'entity_id' => $client->id,
        ]);

        $this->clientService->update($client, ClientData::fromArray([
            'user_id' => $owner->id,
            'company_name' => 'Audit Co Updated',
            'country' => 'BE',
            'status' => ClientStatus::Active->value,
        ]));

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_UPDATED,
            'entity_id' => $client->id,
        ]);

        $this->clientService->suspend($client->fresh(), 'Non-payment');
        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_STATUS_SUSPENDED,
            'entity_id' => $client->id,
        ]);

        $suspendedLog = AuditLog::query()
            ->where('action', ClientAuditLogger::ACTION_STATUS_SUSPENDED)
            ->where('entity_id', $client->id)
            ->firstOrFail();
        $this->assertSame('active', $suspendedLog->before['status']);
        $this->assertSame('suspended', $suspendedLog->after['status']);
        $this->assertSame('Non-payment', $suspendedLog->after['reason']);

        $this->clientService->unsuspend($client->fresh());
        $this->clientService->close($client->fresh());
        $this->clientService->reopen($client->fresh());

        $this->assertDatabaseHas('audit_logs', ['action' => ClientAuditLogger::ACTION_STATUS_UNSUSPENDED, 'entity_id' => $client->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => ClientAuditLogger::ACTION_STATUS_CLOSED, 'entity_id' => $client->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => ClientAuditLogger::ACTION_STATUS_REOPENED, 'entity_id' => $client->id]);

        $this->clientService->delete($client->fresh());

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_DELETED,
            'entity_id' => $client->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_membership_changes_are_audited(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($admin);

        $client = $this->clientService->create(ClientData::fromArray([
            'user_id' => $owner->id,
            'company_name' => 'Members Audit Co',
            'status' => ClientStatus::Active->value,
        ]));

        $membership = $this->clientService->addMember($client, ClientMembershipData::fromArray([
            'user_id' => $member->id,
            'role' => ClientMembershipRole::Billing->value,
        ]));

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_MEMBER_ADDED,
            'entity_id' => $client->id,
        ]);

        $this->clientService->updateMember($membership, ClientMembershipData::fromArray([
            'user_id' => $member->id,
            'role' => ClientMembershipRole::Manager->value,
        ]));

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_MEMBER_UPDATED,
            'entity_id' => $client->id,
        ]);

        $this->clientService->removeMember($client, $membership->fresh());

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_MEMBER_REMOVED,
            'entity_id' => $client->id,
        ]);
    }

    public function test_invitation_actions_are_audited_via_client_audit_logger(): void
    {
        Notification::fake();

        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create();
        $invited = User::factory()->withRole('client')->create([
            'email' => 'invite-audit@corepanel.test',
        ]);
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin);

        [, $token] = app(ClientInvitationService::class)->createInvitation(
            $client,
            $invited->email,
            ClientMembershipRole::User,
            null,
            $admin,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_INVITATION_CREATED,
            'entity_id' => $client->id,
            'actor_id' => $admin->id,
        ]);

        $this->actingAs($invited)
            ->get(route('client.invitations.accept', ['token' => $token]))
            ->assertRedirect(route('client.dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_INVITATION_ACCEPTED,
            'entity_id' => $client->id,
            'actor_id' => $invited->id,
        ]);
    }

    public function test_audit_logging_can_be_disabled(): void
    {
        config(['corepanel.clients.audit.enabled' => false]);

        $admin = User::factory()->withRole('admin')->create();
        $this->actingAs($admin);

        $this->clientService->create(ClientData::fromArray([
            'company_name' => 'Silent Co',
            'status' => ClientStatus::Active->value,
        ]));

        $this->assertDatabaseMissing('audit_logs', [
            'action' => ClientAuditLogger::ACTION_CREATED,
        ]);
    }

    public function test_admin_http_actions_write_audit_logs(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create([
            'company_name' => 'Http Audit Co',
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.suspend', $client), ['reason' => 'Review'])
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_STATUS_SUSPENDED,
            'entity_id' => $client->id,
            'actor_id' => $admin->id,
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.clients.destroy', $client))
            ->assertRedirect(route('admin.clients.index'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_DELETED,
            'entity_id' => $client->id,
        ]);
    }
}
