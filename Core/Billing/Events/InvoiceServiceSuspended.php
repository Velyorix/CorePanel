<?php

namespace Core\Billing\Events;

use Core\Billing\Models\Invoice;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when services linked to an overdue invoice are suspended.
 */
class InvoiceServiceSuspended implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<int>  $serviceIds
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly array $serviceIds = [],
    ) {
    }
}
