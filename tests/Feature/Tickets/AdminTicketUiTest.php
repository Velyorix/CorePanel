<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use Core\Billing\Models\Invoice;
use Core\Clients\Models\Client;
use Core\Orders\Models\Order;
use Core\Services\Models\Service;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketMessage;
use Core\Tickets\Services\TicketAttachmentService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTicketUiTest extends TestCase
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
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-tickets',
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

    public function test_admin_can_view_tickets_index_and_show(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create(['company_name' => 'Acme Hosting']);
        $ticket = Ticket::factory()->numbered('TK-2026-00042')->create([
            'client_id' => $client->id,
            'subject' => 'DNS not resolving',
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::High,
        ]);
        TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => 'Looking into DNS records.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tickets.index'))
            ->assertOk()
            ->assertSee('TK-2026-00042')
            ->assertSee('Acme Hosting')
            ->assertSee('DNS not resolving');

        $this->actingAs($admin)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('TK-2026-00042')
            ->assertSee('Looking into DNS records.')
            ->assertSee('Acme Hosting');
    }

    public function test_admin_ticket_show_displays_contextual_links(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create(['company_name' => 'Linked Client']);
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'hostname' => 'mail.example.test',
        ]);
        $order = Order::factory()->paid()->create(['client_id' => $client->id]);
        $invoice = Invoice::factory()->unpaid()->create(['client_id' => $client->id]);
        $ticket = Ticket::factory()->numbered('TK-2026-00077')->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'order_id' => $order->id,
            'invoice_id' => $invoice->id,
            'subject' => 'Context linked ticket',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('mail.example.test')
            ->assertSee($order->order_number)
            ->assertSee($invoice->invoice_number)
            ->assertSee(route('admin.services.show', $service), false)
            ->assertSee(route('admin.orders.show', $order), false)
            ->assertSee(route('admin.invoices.show', $invoice), false);
    }

    public function test_admin_can_filter_tickets_by_status(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $open = Ticket::factory()->numbered('TK-2026-00001')->create([
            'subject' => 'Open ticket subject',
            'status' => TicketStatus::Open,
        ]);
        Ticket::factory()->numbered('TK-2026-00002')->closed()->create([
            'subject' => 'Closed ticket subject',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tickets.index', ['status' => TicketStatus::Open->value]))
            ->assertOk()
            ->assertSee('Open ticket subject')
            ->assertDontSee('Closed ticket subject')
            ->assertSee($open->ticket_number);
    }

    public function test_support_can_reply_assign_and_close_ticket(): void
    {
        $support = User::factory()->withRole('support')->create();
        $assignee = User::factory()->withRole('admin')->create(['name' => 'Desk Admin']);
        $ticket = Ticket::factory()->numbered('TK-2026-00010')->create([
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Normal,
        ]);

        $this->actingAs($support)
            ->post(route('admin.tickets.reply', $ticket), [
                'message' => 'We are checking this now.',
                'files' => [
                    UploadedFile::fake()->create('note.txt', 5, 'text/plain'),
                ],
            ])
            ->assertRedirect(route('admin.tickets.show', $ticket))
            ->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Answered, $ticket->status);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $support->id,
            'message' => 'We are checking this now.',
        ]);

        $this->actingAs($support)
            ->post(route('admin.tickets.assign', $ticket), [
                'assigned_to' => $assignee->id,
            ])
            ->assertRedirect(route('admin.tickets.show', $ticket))
            ->assertSessionHas('status');

        $this->assertSame($assignee->id, $ticket->fresh()->assigned_to);

        $this->actingAs($support)
            ->post(route('admin.tickets.priority', $ticket), [
                'priority' => TicketPriority::Urgent->value,
            ])
            ->assertRedirect(route('admin.tickets.show', $ticket));

        $this->assertSame(TicketPriority::Urgent, $ticket->fresh()->priority);

        $this->actingAs($support)
            ->post(route('admin.tickets.close', $ticket))
            ->assertRedirect(route('admin.tickets.show', $ticket));

        $this->assertSame(TicketStatus::Closed, $ticket->fresh()->status);

        $this->actingAs($support)
            ->post(route('admin.tickets.reopen', $ticket))
            ->assertRedirect(route('admin.tickets.show', $ticket));

        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_admin_can_download_ticket_attachment(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $ticket = Ticket::factory()->numbered('TK-2026-00077')->create();

        $stored = app(TicketAttachmentService::class)->storeMany($ticket, [
            UploadedFile::fake()->create('server.txt', 8, 'text/plain'),
        ]);

        TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => 'See attachment',
            'attachments' => $stored,
        ]);

        $response = $this->actingAs($admin)->get(
            route('admin.tickets.attachments.download', [$ticket, $stored[0]['id']]),
        );

        $response->assertOk();
        $this->assertStringContainsString(
            'text/plain',
            (string) $response->headers->get('content-type'),
        );
    }

    public function test_client_role_cannot_access_admin_tickets(): void
    {
        $clientUser = User::factory()->withRole('client')->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($clientUser)
            ->get(route('admin.tickets.index'))
            ->assertForbidden();

        $this->actingAs($clientUser)
            ->get(route('admin.tickets.show', $ticket))
            ->assertForbidden();

        $this->actingAs($clientUser)
            ->post(route('admin.tickets.reply', $ticket), ['message' => 'Nope'])
            ->assertForbidden();
    }
}
