<?php

namespace Core\Tickets\Events;

use Core\Tickets\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a support ticket is created.
 */
class TicketCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
    ) {
    }
}
