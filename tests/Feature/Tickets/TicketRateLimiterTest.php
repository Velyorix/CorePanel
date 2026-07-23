<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use Core\Clients\Models\Client;
use Core\Tickets\DataTransferObjects\TicketData;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Services\TicketRateLimiter;
use Core\Tickets\Services\TicketService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TicketRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $tickets;

    private TicketRateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->tickets = app(TicketService::class);
        $this->rateLimiter = app(TicketRateLimiter::class);

        config([
            'corepanel.tickets.rate_limit.create.max_attempts' => 2,
            'corepanel.tickets.rate_limit.create.decay_seconds' => 3600,
            'corepanel.tickets.rate_limit.reply.max_attempts' => 2,
            'corepanel.tickets.rate_limit.reply.decay_seconds' => 3600,
            'corepanel.tickets.notifications.enabled' => false,
            'corepanel.tickets.numbering.prefix' => 'TK',
            'corepanel.tickets.numbering.padding' => 5,
            'corepanel.tickets.numbering.include_year' => true,
            'corepanel.tickets.numbering.reset_yearly' => true,
            'corepanel.tickets.numbering.separator' => '-',
        ]);
    }

    public function test_create_is_blocked_after_max_attempts(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'First ticket',
            'message' => 'Body one',
        ]));
        $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Second ticket',
            'message' => 'Body two',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many tickets created');

        $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Third ticket',
            'message' => 'Body three',
        ]));
    }

    public function test_client_reply_is_blocked_after_max_attempts(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);

        $this->tickets->reply($ticket, $owner, 'Reply one');
        $this->tickets->reply($ticket, $owner, 'Reply two');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many ticket replies');

        $this->tickets->reply($ticket, $owner, 'Reply three');
    }

    public function test_staff_reply_is_not_rate_limited(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $staff = User::factory()->withRole('support')->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);

        $this->tickets->reply($ticket, $staff, 'Staff reply one');
        $this->tickets->reply($ticket, $staff, 'Staff reply two');
        $this->tickets->reply($ticket, $staff, 'Staff reply three');

        $this->assertSame(3, $ticket->messages()->where('user_id', $staff->id)->count());
    }

    public function test_create_rate_limit_clears_after_reset(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);

        $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'First',
            'message' => 'Body',
        ]));
        $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'Second',
            'message' => 'Body',
        ]));

        $this->rateLimiter->clearCreate($owner);

        $ticket = $this->tickets->create($client, $owner, TicketData::fromArray([
            'subject' => 'After clear',
            'message' => 'Body',
        ]));

        $this->assertSame('After clear', $ticket->subject);
    }
}
