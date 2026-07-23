<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Tickets\DataTransferObjects\TicketData;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Notifications\TicketOpenedNotification;
use Core\Tickets\Notifications\TicketReplyNotification;
use Core\Tickets\Services\TicketService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_ticket_create_notifies_support_staff(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $support = User::factory()->withRole('support')->create();

        $ticket = $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Need help',
            'message' => 'Something broke.',
        ]));

        Notification::assertSentTo(
            $support,
            TicketOpenedNotification::class,
            fn (TicketOpenedNotification $notification): bool => $notification->ticket->is($ticket),
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

        $message = $this->tickets->reply($ticket, $owner, 'Any update?');

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

        $message = $this->tickets->reply($ticket, $staff, 'We are investigating.');

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

        $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Silent ticket',
            'message' => 'No mail please.',
        ]));

        Notification::assertNothingSent();
    }
}
