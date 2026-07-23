<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use Core\Billing\Models\Invoice;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\Models\Order;
use Core\Services\Models\Service;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketCategory;
use Core\Tickets\Models\TicketMessage;
use Core\Tickets\Services\TicketAttachmentService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientTicketUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        Storage::fake('local');

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-tickets',
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

    public function test_client_can_view_own_tickets_index_and_show(): void
    {
        [$user, $client] = $this->makeClientUser();
        $ticket = Ticket::factory()->numbered('TK-2026-00011')->create([
            'client_id' => $client->id,
            'subject' => 'Mail delivery issue',
            'status' => TicketStatus::Open,
        ]);
        TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'Emails are bouncing.',
        ]);

        $this->actingAs($user)
            ->get(route('client.tickets.index'))
            ->assertOk()
            ->assertSee('TK-2026-00011')
            ->assertSee('Mail delivery issue');

        $this->actingAs($user)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('TK-2026-00011')
            ->assertSee('Emails are bouncing.');
    }

    public function test_client_cannot_view_another_clients_ticket(): void
    {
        [$user] = $this->makeClientUser();
        $other = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $other->id]);

        $this->actingAs($user)
            ->get(route('client.tickets.show', $ticket))
            ->assertNotFound();
    }

    public function test_client_can_create_ticket_with_attachment(): void
    {
        [$user, $client] = $this->makeClientUser();
        $category = TicketCategory::factory()->create(['name' => 'Technical', 'slug' => 'technical']);

        $this->actingAs($user)
            ->get(route('client.tickets.create'))
            ->assertOk()
            ->assertSee('Open a support ticket');

        $response = $this->actingAs($user)
            ->post(route('client.tickets.store'), [
                'subject' => 'Cannot access panel',
                'message' => 'Login fails after password reset.',
                'category_id' => $category->id,
                'priority' => TicketPriority::High->value,
                'files' => [
                    UploadedFile::fake()->create('screenshot.txt', 4, 'text/plain'),
                ],
            ]);

        $ticket = Ticket::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($ticket);
        $response->assertRedirect(route('client.tickets.show', $ticket));
        $this->assertSame('Cannot access panel', $ticket->subject);
        $this->assertSame(TicketPriority::High, $ticket->priority);
        $this->assertNotNull($ticket->ticket_number);
        $this->assertCount(1, $ticket->messages);
        $this->assertNotEmpty($ticket->messages->first()->attachments);
    }

    public function test_client_can_create_ticket_with_contextual_links(): void
    {
        [$user, $client] = $this->makeClientUser();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'hostname' => 'panel.example.test',
        ]);
        $order = Order::factory()->paid()->create(['client_id' => $client->id]);
        $invoice = Invoice::factory()->unpaid()->create(['client_id' => $client->id]);

        $this->actingAs($user)
            ->get(route('client.tickets.create'))
            ->assertOk()
            ->assertSee('panel.example.test')
            ->assertSee($order->order_number)
            ->assertSee($invoice->invoice_number);

        $response = $this->actingAs($user)
            ->post(route('client.tickets.store'), [
                'subject' => 'Issue with linked service',
                'message' => 'Service is unreachable.',
                'service_id' => $service->id,
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'priority' => TicketPriority::Normal->value,
            ]);

        $ticket = Ticket::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($ticket);
        $response->assertRedirect(route('client.tickets.show', $ticket));
        $this->assertSame($service->id, $ticket->service_id);
        $this->assertSame($order->id, $ticket->order_id);
        $this->assertSame($invoice->id, $ticket->invoice_id);

        $this->actingAs($user)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('panel.example.test')
            ->assertSee($order->order_number)
            ->assertSee($invoice->invoice_number)
            ->assertSee(route('client.services.show', $service), false);
    }

    public function test_client_cannot_link_another_clients_service(): void
    {
        [$user] = $this->makeClientUser();
        $foreignService = Service::factory()->create([
            'hostname' => 'foreign.example.test',
        ]);

        $this->actingAs($user)
            ->post(route('client.tickets.store'), [
                'subject' => 'Bad link attempt',
                'message' => 'Should be rejected.',
                'service_id' => $foreignService->id,
            ])
            ->assertSessionHasErrors('service_id');
    }

    public function test_client_can_reply_to_own_open_ticket(): void
    {
        [$user, $client] = $this->makeClientUser();
        $ticket = Ticket::factory()->answered()->create([
            'client_id' => $client->id,
            'subject' => 'Need follow-up',
        ]);

        $this->actingAs($user)
            ->post(route('client.tickets.reply', $ticket), [
                'message' => 'Still seeing the same error.',
            ])
            ->assertRedirect(route('client.tickets.show', $ticket))
            ->assertSessionHas('status');

        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'Still seeing the same error.',
        ]);
    }

    public function test_client_cannot_reply_to_closed_ticket(): void
    {
        [$user, $client] = $this->makeClientUser();
        $ticket = Ticket::factory()->closed()->create([
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->post(route('client.tickets.reply', $ticket), [
                'message' => 'Please reopen',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('ticket');
    }

    public function test_client_can_download_own_ticket_attachment(): void
    {
        [$user, $client] = $this->makeClientUser();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);

        $stored = app(TicketAttachmentService::class)->storeMany($ticket, [
            UploadedFile::fake()->create('logs.txt', 6, 'text/plain'),
        ]);

        TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'Attached logs',
            'attachments' => $stored,
        ]);

        $response = $this->actingAs($user)->get(
            route('client.tickets.attachments.download', [$ticket, $stored[0]['id']]),
        );

        $response->assertOk();
        $this->assertStringContainsString(
            'text/plain',
            (string) $response->headers->get('content-type'),
        );
    }

    public function test_admin_without_client_access_cannot_view_client_tickets(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.tickets.index'))
            ->assertForbidden();
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
