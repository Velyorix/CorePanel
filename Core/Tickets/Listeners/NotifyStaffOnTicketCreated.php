<?php

namespace Core\Tickets\Listeners;

use Core\Tickets\Events\TicketCreated;
use Core\Tickets\Services\TicketNotificationService;

class NotifyStaffOnTicketCreated
{
    public function __construct(
        private readonly TicketNotificationService $notifications,
    ) {
    }

    public function handle(TicketCreated $event): void
    {
        $this->notifications->notifyOpened($event->ticket);
    }
}
