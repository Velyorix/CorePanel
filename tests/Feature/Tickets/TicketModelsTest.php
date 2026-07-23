<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use Core\Clients\Models\Client;
use Core\Tickets\Enums\TicketCategoryStatus;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketCategory;
use Core\Tickets\Models\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_relations_and_casts(): void
    {
        $client = Client::factory()->create();
        $category = TicketCategory::factory()->create([
            'name' => 'Billing',
            'slug' => 'billing',
        ]);
        $assignee = User::factory()->create();

        $ticket = Ticket::factory()
            ->forCategory($category)
            ->assignedTo($assignee)
            ->withPriority(TicketPriority::High)
            ->create([
                'client_id' => $client->id,
                'subject' => 'Invoice question',
            ]);

        $message = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $assignee->id,
            'message' => 'Looking into this.',
        ]);

        $this->assertTrue($ticket->client->is($client));
        $this->assertTrue($ticket->category->is($category));
        $this->assertTrue($ticket->assignee->is($assignee));
        $this->assertTrue($ticket->messages->contains($message));
        $this->assertTrue($client->tickets->contains($ticket));
        $this->assertTrue($category->tickets->contains($ticket));
        $this->assertTrue($message->ticket->is($ticket));
        $this->assertTrue($message->author->is($assignee));

        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertSame(TicketPriority::High, $ticket->priority);
        $this->assertSame(TicketCategoryStatus::Active, $category->status);
    }

    public function test_ticket_and_category_support_soft_delete(): void
    {
        $category = TicketCategory::factory()->create(['slug' => 'soft-cat']);
        $ticket = Ticket::factory()->forCategory($category)->create([
            'subject' => 'Soft delete me',
        ]);

        $ticket->delete();
        $category->delete();

        $this->assertSoftDeleted($ticket);
        $this->assertSoftDeleted($category);
    }

    public function test_ticket_message_is_append_only(): void
    {
        $message = TicketMessage::factory()->create([
            'message' => 'Initial body',
        ]);

        $this->assertNotNull($message->created_at);
        $this->assertFalse(isset($message->updated_at));
        $this->assertFalse($message->timestamps);
    }

    public function test_status_and_priority_enums_expose_values(): void
    {
        $this->assertSame([
            'open',
            'in_progress',
            'answered',
            'pending',
            'closed',
        ], TicketStatus::values());

        $this->assertSame([
            'low',
            'normal',
            'high',
            'urgent',
        ], TicketPriority::values());

        $this->assertTrue(TicketStatus::Open->isOpen());
        $this->assertTrue(TicketStatus::Closed->isClosed());
        $this->assertFalse(TicketStatus::Answered->isClosed());
    }
}
