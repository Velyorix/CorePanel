<?php

namespace Core\Billing\Events;

use Core\Billing\Models\Quote;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a sent quote is accepted by the client.
 */
class QuoteAccepted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Quote $quote,
    ) {
    }
}
