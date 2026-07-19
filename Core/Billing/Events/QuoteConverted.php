<?php

namespace Core\Billing\Events;

use Core\Billing\Models\Invoice;
use Core\Billing\Models\Quote;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a quote is converted into an invoice for the first time.
 */
class QuoteConverted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Quote $quote,
        public readonly Invoice $invoice,
    ) {
    }
}
