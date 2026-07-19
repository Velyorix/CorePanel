<?php

namespace Core\Services\Listeners;

use Core\Orders\Events\OrderPaid;
use Core\Services\Services\ServiceCreationService;

class CreateServicesOnOrderPaid
{
    public function __construct(
        private readonly ServiceCreationService $creation,
    ) {
    }

    public function handle(OrderPaid $event): void
    {
        $this->creation->createFromPaidOrder($event->order);
    }
}
