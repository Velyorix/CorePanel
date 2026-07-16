<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientNote;
use Core\Clients\Services\ClientAuditLogger;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClientNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-client-notes',
            'corepanel.clients.audit.enabled' => true,
        ]);
    }

    public function test_admin_can_add_and_delete_internal_note(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create([
            'company_name' => 'Notes Co',
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.notes.store', $client), [
                'body' => 'Called the client about unpaid invoice.',
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $note = ClientNote::query()->where('client_id', $client->id)->firstOrFail();

        $this->assertSame($admin->id, $note->user_id);
        $this->assertSame('Called the client about unpaid invoice.', $note->body);

        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_NOTE_CREATED,
            'entity_id' => $client->id,
            'actor_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('Called the client about unpaid invoice.')
            ->assertSee(__('Internal notes'), false)
            ->assertSee(__('Staff-only notes'), false);

        $this->actingAs($admin)
            ->delete(route('admin.clients.notes.destroy', [$client, $note]))
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertDatabaseMissing('client_notes', ['id' => $note->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => ClientAuditLogger::ACTION_NOTE_DELETED,
            'entity_id' => $client->id,
        ]);
    }

    public function test_support_can_view_notes_but_cannot_create(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $support = User::factory()->withRole('support')->create();
        $client = Client::factory()->create(['status' => ClientStatus::Active]);

        ClientNote::query()->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'body' => 'Visible to support staff.',
        ]);

        $this->actingAs($support)
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('Visible to support staff.')
            ->assertDontSee(__('Save note'), false);

        $this->actingAs($support)
            ->post(route('admin.clients.notes.store', $client), [
                'body' => 'Support should not create notes.',
            ])
            ->assertForbidden();
    }

    public function test_note_body_is_required(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create(['status' => ClientStatus::Active]);

        $this->actingAs($admin)
            ->from(route('admin.clients.show', $client))
            ->post(route('admin.clients.notes.store', $client), [
                'body' => '',
            ])
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHasErrors('body');
    }

    public function test_cannot_delete_note_from_another_client(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create(['status' => ClientStatus::Active]);
        $otherClient = Client::factory()->create(['status' => ClientStatus::Active]);

        $note = ClientNote::query()->create([
            'client_id' => $otherClient->id,
            'user_id' => $admin->id,
            'body' => 'Belongs elsewhere.',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.clients.show', $client))
            ->delete(route('admin.clients.notes.destroy', [$client, $note]))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHasErrors('note');

        $this->assertDatabaseHas('client_notes', ['id' => $note->id]);
    }

    public function test_support_cannot_delete_notes(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $support = User::factory()->withRole('support')->create();
        $client = Client::factory()->create(['status' => ClientStatus::Active]);

        $note = ClientNote::query()->create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'body' => 'Support cannot delete this.',
        ]);

        $this->actingAs($support)
            ->delete(route('admin.clients.notes.destroy', [$client, $note]))
            ->assertForbidden();

        $this->assertDatabaseHas('client_notes', ['id' => $note->id]);
    }
}
