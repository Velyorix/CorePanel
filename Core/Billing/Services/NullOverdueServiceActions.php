<?php

namespace Core\Billing\Services;

use Core\Billing\Contracts\OverdueServiceActions;
use Core\Billing\Models\Invoice;

/**
 * Default overdue actions stub kept for tests and explicit no-op binding.
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
