<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Tickets\Enums\TicketStatus;
use Core\Tickets\Models\Ticket;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end support ticket smoke: create → staff reply → status.
 */
class TicketAcceptanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        Storage::fake('local');
        Notification::fake();

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.ticket-acceptance',
            'corepanel.tickets.notifications.enabled' => false,
            'corepanel.tickets.rate_limit.create.max_attempts' => 100,
            'corepanel.tickets.rate_limit.reply.max_attempts' => 100,
            'corepanel.tickets.numbering.prefix' => 'TK',
            'corepanel.tickets.numbering.padding' => 5,
            'corepanel.tickets.numbering.include_year' => true,
            'corepanel.tickets.numbering.reset_yearly' => true,
            'corepanel.tickets.numbering.separator' => '-',
            'corepanel.tickets.attachments.disk' => 'local',
            'corepanel.tickets.attachments.path_prefix' => 'tickets',
            'corepanel.tickets.attachments.max_files' => 5,
            'corepanel.tickets.attachments.max_kilobytes' => 5120,
            'corepanel.tickets.attachments.allowed_extensions' => ['png', 'txt', 'pdf'],
            'corepanel.tickets.attachments.allowed_mimes' => [
                'image/png',
                'text/plain',
                'application/pdf',
            ],
        ]);
    }

    public function test_client_creates_ticket_admin_replies_and_status_updates(): void
    {
        [$clientUser, $client] = $this->makeClientUser();
        $support = User::factory()->withRole('support')->create(['name' => 'Support Agent']);

        $this->actingAs($clientUser)
            ->post(route('client.tickets.store'), [
                'subject' => 'Server unreachable',
                'message' => 'Cannot SSH into the VPS since this morning.',
                'files' => [
                    UploadedFile::fake()->create('diag.txt', 4, 'text/plain'),
                ],
            ])
            ->assertRedirect();

        $ticket = Ticket::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($ticket);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertMatchesRegularExpression('/^TK-\d{4}-\d{5}$/', (string) $ticket->ticket_number);
        $this->assertCount(1, $ticket->messages);
        $this->assertNotEmpty($ticket->messages->first()->attachments);

        $this->actingAs($support)
            ->post(route('admin.tickets.reply', $ticket), [
                'message' => 'We restarted the host node. Please retry SSH.',
            ])
            ->assertRedirect(route('admin.tickets.show', $ticket))
            ->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Answered, $ticket->status);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $support->id,
            'message' => 'We restarted the host node. Please retry SSH.',
        ]);

        $this->actingAs($clientUser)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertSee('Server unreachable')
            ->assertSee('We restarted the host node. Please retry SSH.');
    }

    /**
     * @param  array<string, mixed>  $clientAttributes
     * @return array{0: User, 1: Client}
     */
    private function makeClientUser(array $clientAttributes = []): array
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            ...$clientAttributes,
        ]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        return [$user, $client];
    }
}
