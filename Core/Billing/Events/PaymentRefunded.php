<?php

namespace Core\Billing\Events;

use Core\Billing\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a completed payment is refunded via the gateway.
 */
class PaymentRefunded implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $refundAmount,
    ) {
    }
}
