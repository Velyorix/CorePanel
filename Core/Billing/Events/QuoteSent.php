<?php

namespace Core\Billing\Events;

use Core\Billing\Models\Quote;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a draft quote is sent to the client.
 */
class QuoteSent implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Quote $quote,
    ) {
    }
}
