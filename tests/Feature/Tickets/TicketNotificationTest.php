<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Tickets\DataTransferObjects\TicketData;
use Core\Tickets\Events\TicketCreated;
use Core\Tickets\Events\TicketReplied;
use Core\Tickets\Listeners\NotifyParticipantsOnTicketReplied;
use Core\Tickets\Listeners\NotifyStaffOnTicketCreated;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Notifications\TicketOpenedNotification;
use Core\Tickets\Notifications\TicketReplyNotification;
use Core\Tickets\Services\TicketNotificationService;
use Core\Tickets\Services\TicketService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $tickets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->tickets = app(TicketService::class);

        config([
            'corepanel.tickets.notifications.enabled' => true,
            'corepanel.tickets.rate_limit.create.max_attempts' => 100,
            'corepanel.tickets.rate_limit.reply.max_attempts' => 100,
            'corepanel.tickets.numbering.prefix' => 'TK',
            'corepanel.tickets.numbering.padding' => 5,
            'corepanel.tickets.numbering.include_year' => true,
            'corepanel.tickets.numbering.reset_yearly' => true,
            'corepanel.tickets.numbering.separator' => '-',
        ]);
    }

    public function test_ticket_create_dispatches_ticket_created_event(): void
    {
        Event::fake([TicketCreated::class]);

        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $ticket = $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Need help',
            'message' => 'Something broke.',
        ]));

        Event::assertDispatched(
            TicketCreated::class,
            fn (TicketCreated $event): bool => $event->ticket->is($ticket),
        );
    }

    public function test_ticket_create_notifies_support_staff_end_to_end(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $support = User::factory()->withRole('support')->create();

        $ticket = $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Need help end to end',
            'message' => 'Something broke.',
        ]));

        Notification::assertSentTo(
            $support,
            TicketOpenedNotification::class,
            fn (TicketOpenedNotification $notification): bool => $notification->ticket->is($ticket),
        );
    }

    public function test_ticket_create_notifies_support_staff(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $support = User::factory()->withRole('support')->create();
        $ticket = Ticket::factory()->create([
            'client_id' => $client->id,
            'subject' => 'Need help',
        ]);

        app(NotifyStaffOnTicketCreated::class)->handle(new TicketCreated($ticket));

        Notification::assertSentTo(
            $support,
            TicketOpenedNotification::class,
            fn (TicketOpenedNotification $notification): bool => $notification->ticket->is($ticket),
        );
    }

    public function test_client_reply_dispatches_ticket_replied_event(): void
    {
        Event::fake([TicketReplied::class]);

        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);

        $message = $this->tickets->reply($ticket, $owner, 'Any update?');

        Event::assertDispatched(
            TicketReplied::class,
            fn (TicketReplied $event): bool => $event->ticket->is($ticket)
                && $event->message->is($message)
                && $event->author->is($owner),
        );
    }

    public function test_client_reply_notifies_assignee(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $assignee = User::factory()->withRole('support')->create();
        $otherStaff = User::factory()->withRole('admin')->create();

        $ticket = Ticket::factory()->assignedTo($assignee)->create([
            'client_id' => $client->id,
            'subject' => 'Assigned ticket',
        ]);
        $message = $ticket->messages()->create([
            'user_id' => $owner->id,
            'message' => 'Any update?',
            'created_at' => now(),
        ]);

        app(NotifyParticipantsOnTicketReplied::class)->handle(
            new TicketReplied($ticket, $message, $owner),
        );

        Notification::assertSentTo(
            $assignee,
            TicketReplyNotification::class,
            function (TicketReplyNotification $notification) use ($ticket, $message, $owner): bool {
                return $notification->ticket->is($ticket)
                    && $notification->message->is($message)
                    && $notification->author->is($owner)
                    && $notification->forStaff === true;
            },
        );

        Notification::assertNotSentTo($otherStaff, TicketReplyNotification::class);
    }

    public function test_staff_reply_notifies_client_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $client->users()->attach($owner->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);
        $staff = User::factory()->withRole('support')->create();
        $ticket = Ticket::factory()->create([
            'client_id' => $client->id,
            'subject' => 'Client notify ticket',
        ]);
        $message = $ticket->messages()->create([
            'user_id' => $staff->id,
            'message' => 'We are investigating.',
            'created_at' => now(),
        ]);

        app(NotifyParticipantsOnTicketReplied::class)->handle(
            new TicketReplied($ticket, $message, $staff),
        );

        Notification::assertSentTo(
            $owner,
            TicketReplyNotification::class,
            function (TicketReplyNotification $notification) use ($ticket, $message, $staff): bool {
                return $notification->ticket->is($ticket)
                    && $notification->message->is($message)
                    && $notification->author->is($staff)
                    && $notification->forStaff === false;
            },
        );

        Notification::assertNotSentTo($staff, TicketReplyNotification::class);
    }

    public function test_notifications_can_be_disabled(): void
    {
        Notification::fake();
        config(['corepanel.tickets.notifications.enabled' => false]);

        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        User::factory()->withRole('support')->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);

        app(TicketNotificationService::class)->notifyOpened($ticket);

        Notification::assertNothingSent();
    }
}
