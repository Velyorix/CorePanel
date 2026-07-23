<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Tickets\Models\Ticket;

class TicketPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'tickets.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->allows($user, 'tickets.view', $ticket);
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->allows($user, 'tickets.reply', $ticket);
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $this->allows($user, 'tickets.assign', $ticket);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $this->allows($user, 'tickets.close', $ticket);
    }

    public function updatePriority(User $user, Ticket $ticket): bool
    {
        return $this->allows($user, 'tickets.assign', $ticket);
    }
}
