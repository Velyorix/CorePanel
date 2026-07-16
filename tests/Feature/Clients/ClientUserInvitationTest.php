<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Notifications\ClientUserInvitationNotification;
use Core\Clients\Services\ClientInvitationService;
use Core\Clients\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Database\Seeders\RoleAndPermissionSeeder;
use Tests\TestCase;

class ClientUserInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-client-invitations',
        ]);
    }

    public function test_admin_can_create_invitation_and_notification_is_sent(): void
    {
        Notification::fake();

        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $owner->id, 'company_name' => 'Acme Co']);

        $invitedUser = User::factory()->withRole('client')->create([
            'email' => 'invited@corepanel.test',
        ]);
        $invitedUserCore = \Core\Auth\Models\User::query()->whereKey($invitedUser->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.clients.invitations.store', $client), [
                'email' => $invitedUser->email,
                'role' => ClientMembershipRole::Billing->value,
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertDatabaseHas('client_user_invitations', [
            'client_id' => $client->id,
            'email' => $invitedUser->email,
            'role' => ClientMembershipRole::Billing->value,
        ]);

        $this->assertDatabaseMissing('client_users', [
            'client_id' => $client->id,
            'user_id' => $invitedUser->id,
        ]);

        Notification::assertSentTo($invitedUserCore, ClientUserInvitationNotification::class);
    }

    public function test_authenticated_user_can_accept_invitation_creates_membership_and_marks_accepted(): void
    {
        $invitedBy = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $owner->id, 'company_name' => 'Invite Co']);

        $invitedUser = User::factory()->withRole('client')->create([
            'email' => 'member@corepanel.test',
        ]);

        [$invitation, $plainToken] = app(ClientInvitationService::class)->createInvitation(
            client: $client,
            email: $invitedUser->email,
            role: ClientMembershipRole::Admin,
            permissions: null,
            invitedBy: $invitedBy,
        );

        $this->actingAs($invitedUser)
            ->get(route('client.invitations.accept', ['token' => $plainToken]))
            ->assertRedirect(route('client.dashboard'));

        $this->assertDatabaseHas('client_users', [
            'client_id' => $client->id,
            'user_id' => $invitedUser->id,
            'role' => ClientMembershipRole::Admin->value,
        ]);

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_guest_is_redirected_from_accept_invitation(): void
    {
        $invitedBy = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $invitedUser = User::factory()->withRole('client')->create([
            'email' => 'guest@corepanel.test',
        ]);

        [, $plainToken] = app(ClientInvitationService::class)->createInvitation(
            client: $client,
            email: $invitedUser->email,
            role: ClientMembershipRole::User,
            permissions: null,
            invitedBy: $invitedBy,
        );

        $this->get(route('client.invitations.accept', ['token' => $plainToken]))
            ->assertRedirect(route('login'));
    }

    public function test_invalid_token_redirects_with_error(): void
    {
        $invitedUser = User::factory()->withRole('client')->create([
            'email' => 'invalid@corepanel.test',
        ]);

        $this->actingAs($invitedUser)
            ->get(route('client.invitations.accept', ['token' => 'invalid-token']))
            ->assertRedirect(route('client.dashboard'))
            ->assertSessionHasErrors('email');
    }

    public function test_email_mismatch_redirects_with_error(): void
    {
        $invitedBy = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $invitedUser = User::factory()->withRole('client')->create([
            'email' => 'correct@corepanel.test',
        ]);

        $differentUser = User::factory()->withRole('client')->create([
            'email' => 'different@corepanel.test',
        ]);

        [, $plainToken] = app(ClientInvitationService::class)->createInvitation(
            client: $client,
            email: $invitedUser->email,
            role: ClientMembershipRole::User,
            permissions: null,
            invitedBy: $invitedBy,
        );

        $this->actingAs($differentUser)
            ->get(route('client.invitations.accept', ['token' => $plainToken]))
            ->assertRedirect(route('client.dashboard'))
            ->assertSessionHasErrors('email');
    }

    public function test_expired_invitation_cannot_be_used(): void
    {
        $invitedBy = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $invitedUser = User::factory()->withRole('client')->create([
            'email' => 'expired@corepanel.test',
        ]);

        [$invitation, $plainToken] = app(ClientInvitationService::class)->createInvitation(
            client: $client,
            email: $invitedUser->email,
            role: ClientMembershipRole::User,
            permissions: null,
            invitedBy: $invitedBy,
        );

        $invitation->forceFill([
            'expires_at' => now()->subHour(),
        ])->save();

        $this->actingAs($invitedUser)
            ->get(route('client.invitations.accept', ['token' => $plainToken]))
            ->assertRedirect(route('client.dashboard'))
            ->assertSessionHasErrors('email');
    }
}

