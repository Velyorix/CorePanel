<?php

namespace Core\Billing\Events;

use Core\Billing\Models\ClientCreditTransaction;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when credit is newly added to a client's wallet balance.
 */
class ClientCreditAdded implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ClientCreditTransaction $transaction,
    ) {
    }
}
