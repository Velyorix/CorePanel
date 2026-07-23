<?php

namespace Core\Tickets\Events;

use Core\Auth\Models\User;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketMessage;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a reply is posted on a support ticket.
 */
class TicketReplied implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketMessage $message,
        public readonly User $author,
    ) {
    }
}
