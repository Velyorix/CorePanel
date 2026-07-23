<?php

namespace Database\Factories;

use App\Models\User;
use Core\Clients\Models\Client;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'category_id' => null,
            'subject' => fake()->sentence(6),
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Normal,
            'assigned_to' => null,
        ];
    }

    public function forCategory(TicketCategory $category): static
    {
        return $this->state(fn (): array => [
            'category_id' => $category->id,
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (): array => [
            'assigned_to' => $user->id,
            'status' => TicketStatus::InProgress,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::InProgress,
        ]);
    }

    public function answered(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Answered,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Pending,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Closed,
        ]);
    }

    public function withPriority(TicketPriority $priority): static
    {
        return $this->state(fn (): array => [
            'priority' => $priority,
        ]);
    }
}
