<?php

namespace Core\Tickets\Listeners;

use Core\Tickets\Events\TicketReplied;
use Core\Tickets\Services\TicketNotificationService;

class NotifyParticipantsOnTicketReplied
{
    public function __construct(
        private readonly TicketNotificationService $notifications,
    ) {
    }

    public function handle(TicketReplied $event): void
    {
        $this->notifications->notifyReplied(
            $event->ticket,
            $event->message,
            $event->author,
        );
    }
}
