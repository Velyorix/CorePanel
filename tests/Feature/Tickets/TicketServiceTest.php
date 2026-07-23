<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUser;
use Core\Tickets\DataTransferObjects\TicketData;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketCategory;
use Core\Tickets\Models\TicketMessage;
use Core\Tickets\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $tickets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tickets = app(TicketService::class);

        config([
            'corepanel.tickets.numbering.prefix' => 'TK',
            'corepanel.tickets.numbering.padding' => 5,
            'corepanel.tickets.numbering.include_year' => true,
            'corepanel.tickets.numbering.reset_yearly' => true,
            'corepanel.tickets.numbering.separator' => '-',
        ]);
    }

    public function test_ticket_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(TicketService::class),
            app(TicketService::class),
        );
    }

    public function test_create_persists_ticket_with_initial_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00'));

        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $category = TicketCategory::factory()->create(['slug' => 'general']);

        $ticket = $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Need help with DNS',
            'message' => 'Records are not propagating.',
            'category_id' => $category->id,
            'priority' => TicketPriority::High->value,
        ]));

        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertSame(TicketPriority::High, $ticket->priority);
        $this->assertSame('TK-2026-00001', $ticket->ticket_number);
        $this->assertTrue($ticket->category->is($category));
        $this->assertNull($ticket->assigned_to);
        $this->assertCount(1, $ticket->messages);
        $this->assertSame('Records are not propagating.', $ticket->messages->first()->message);
        $this->assertTrue($ticket->messages->first()->author->is($owner));
    }

    public function test_create_rejects_empty_subject_or_message(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The ticket subject cannot be empty.');

        $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => '   ',
            'message' => 'Body',
        ]));
    }

    public function test_create_rejects_unknown_category(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ticket category [999999] does not exist.');

        $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Broken category',
            'message' => 'Hello',
            'category_id' => 999999,
        ]));
    }

    public function test_staff_reply_marks_ticket_answered(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $staff = User::factory()->create();

        $ticket = $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Support request',
            'message' => 'Initial client message',
        ]));

        $reply = $this->tickets->reply($ticket, $staff, 'We are looking into this.');

        $this->assertInstanceOf(TicketMessage::class, $reply);
        $this->assertSame(TicketStatus::Answered, $ticket->fresh()->status);
        $this->assertSame(2, $ticket->messages()->count());
    }

    public function test_client_reply_reopens_conversation_status(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create();

        ClientUser::query()->create([
            'client_id' => $client->id,
            'user_id' => $member->id,
            'role' => ClientMembershipRole::User,
            'permissions' => null,
            'created_at' => now(),
        ]);

        $ticket = Ticket::factory()->answered()->create([
            'client_id' => $client->id,
            'subject' => 'Follow-up needed',
        ]);

        $this->tickets->reply($ticket, $member, 'Thanks, still broken on my side.');

        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_reply_on_closed_ticket_is_rejected(): void
    {
        $staff = User::factory()->create();
        $ticket = Ticket::factory()->closed()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Closed tickets cannot receive replies. Reopen the ticket first.');

        $this->tickets->reply($ticket, $staff, 'Too late');
    }

    public function test_assign_moves_open_ticket_to_in_progress(): void
    {
        $staff = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Open,
        ]);

        $assigned = $this->tickets->assign($ticket, $staff);

        $this->assertTrue($assigned->assignee->is($staff));
        $this->assertSame(TicketStatus::InProgress, $assigned->status);
    }

    public function test_assign_null_unassigns_without_forcing_status(): void
    {
        $staff = User::factory()->create();
        $ticket = Ticket::factory()->assignedTo($staff)->create();

        $unassigned = $this->tickets->assign($ticket, null);

        $this->assertNull($unassigned->assigned_to);
        $this->assertSame(TicketStatus::InProgress, $unassigned->status);
    }

    public function test_close_and_reopen_lifecycle(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Answered,
        ]);

        $closed = $this->tickets->close($ticket);
        $this->assertSame(TicketStatus::Closed, $closed->status);

        $closedAgain = $this->tickets->close($closed);
        $this->assertSame(TicketStatus::Closed, $closedAgain->status);

        $reopened = $this->tickets->reopen($closedAgain);
        $this->assertSame(TicketStatus::Open, $reopened->status);
    }

    public function test_reopen_rejects_non_closed_tickets(): void
    {
        $ticket = Ticket::factory()->answered()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only closed tickets can be reopened.');

        $this->tickets->reopen($ticket);
    }

    public function test_set_priority_updates_ticket(): void
    {
        $ticket = Ticket::factory()->withPriority(TicketPriority::Normal)->create();

        $updated = $this->tickets->setPriority($ticket, TicketPriority::Urgent);

        $this->assertSame(TicketPriority::Urgent, $updated->priority);
    }

    public function test_create_stores_attachment_metadata_list(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $ticket = $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'With attachment meta',
            'message' => 'See file',
            'attachments' => [
                ['name' => 'screenshot.png', 'size' => 1200],
            ],
        ]));

        $this->assertSame(
            [['name' => 'screenshot.png', 'size' => 1200]],
            $ticket->messages->first()->attachments,
        );
    }

    public function test_create_rejects_non_list_attachments(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ticket attachments must be a list.');

        $this->tickets->create($client, $owner, new TicketData(
            subject: 'Bad attachments',
            message: 'Hello',
            attachments: ['name' => 'oops.png'],
        ));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
