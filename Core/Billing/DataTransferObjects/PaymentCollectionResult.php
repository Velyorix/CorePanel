<?php

namespace Core\Billing\DataTransferObjects;

use Core\Billing\Models\Payment;

/**
 * Result of collecting payment with optional wallet credit applied first.
 */
final readonly class PaymentCollectionResult
{
    public function __construct(
        public ?Payment $creditPayment = null,
        public ?Payment $gatewayPayment = null,
    ) {
    }

    /**
     * @return list<Payment>
     */
    public function payments(): array
    {
        return array_values(array_filter(
            [$this->creditPayment, $this->gatewayPayment],
            static fn (?Payment $payment): bool => $payment !== null,
        ));
    }

    public function paidWithCreditOnly(): bool
    {
        return $this->creditPayment !== null && $this->gatewayPayment === null;
    }

    public function creditApplied(): bool
    {
        return $this->creditPayment !== null;
    }
}
