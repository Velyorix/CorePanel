<?php

namespace Core\Billing\Services;

use Core\Billing\Contracts\OverdueServiceActions;
use Core\Billing\Models\Invoice;

/**
 * Default overdue actions before the services engine is available.
 * No-op; real suspend/terminate is wired later via this contract.
 */
class NullOverdueServiceActions implements OverdueServiceActions
{
    public function suspend(Invoice $invoice, int $serviceId, string $reason): void
    {
    }

    public function terminate(Invoice $invoice, int $serviceId, string $reason): void
    {
    }
}
