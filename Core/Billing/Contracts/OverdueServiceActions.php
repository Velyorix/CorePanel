<?php

namespace Core\Billing\Contracts;

use Core\Billing\Models\Invoice;

/**
 * Lifecycle actions for services linked to overdue invoices.
 * Real implementation arrives with the services engine; default is a no-op.
 */
interface OverdueServiceActions
{
    /**
     * Suspend provisioned service(s) for an overdue invoice. Must be idempotent.
     */
    public function suspend(Invoice $invoice, int $serviceId, string $reason): void;

    /**
     * Terminate provisioned service(s) for a long-overdue invoice. Must be idempotent.
     */
    public function terminate(Invoice $invoice, int $serviceId, string $reason): void;
}
