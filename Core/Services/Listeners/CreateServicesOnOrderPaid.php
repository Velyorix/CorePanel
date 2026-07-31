<?php

namespace Core\Services\Listeners;

use Core\Orders\Events\OrderPaid;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Services\ServiceCreationService;

class CreateServicesOnOrderPaid
{
    public function __construct(
        private readonly ServiceCreationService $creation,
        private readonly ProvisioningEngine $provisioning,
    ) {
    }

    public function handle(OrderPaid $event): void
    {
        $created = $this->creation->createFromPaidOrder($event->order);

        foreach ($created as $service) {
            $this->provisioning->queueIfEligible($service);
        }
    }
}
