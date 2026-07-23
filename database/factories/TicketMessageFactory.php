<?php

namespace Database\Factories;

use App\Models\User;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    protected $model = TicketMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'message' => fake()->paragraph(),
            'attachments' => null,
            'created_at' => now(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     */
    public function withAttachments(array $attachments): static
    {
        return $this->state(fn (): array => [
            'attachments' => $attachments,
        ]);
    }
}
