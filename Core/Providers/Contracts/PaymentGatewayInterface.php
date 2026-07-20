<?php

namespace Core\Providers\Contracts;

use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\Models\Payment;

/**
 * Payment provider contract implemented by external gateway modules.
 *
 * Terminology maps to the billing layer as follows:
 * - charge()   → {@see \Core\Billing\Contracts\PaymentGateway::createPayment()}
 * - validate() → {@see \Core\Billing\Contracts\PaymentGateway::verifyPayment()}
 * - refund()   → {@see \Core\Billing\Contracts\PaymentGateway::refundPayment()}
 *
 * Webhook handling remains on the billing {@see \Core\Billing\Contracts\PaymentGateway}
 * contract until gateway modules are wired through the provider registry.
 */
interface PaymentGatewayInterface
{
    /**
     * Stable gateway key used for registry resolution (e.g. stripe, manual_transfer).
     */
    public function key(): string;

    /**
     * Human-readable gateway label for admin and checkout UI.
     */
    public function label(): string;

    /**
     * Charge or initiate a payment with the external provider.
     *
     * The payment row already exists as pending in CorePanel.
     */
    public function charge(Payment $payment, PaymentContext $context): PaymentGatewayResult;

    /**
     * Refund a completed payment (full or partial amount).
     */
    public function refund(Payment $payment, string $amount): PaymentGatewayResult;

    /**
     * Validate or verify the current payment status with the provider.
     */
    public function validate(Payment $payment): PaymentGatewayResult;
}
