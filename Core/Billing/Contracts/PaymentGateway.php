<?php

namespace Core\Billing\Contracts;

use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\DataTransferObjects\PaymentGatewayWebhookResult;
use Core\Billing\Models\Payment;

interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    /**
     * Start or acknowledge a payment with the provider.
     * The payment row already exists as pending.
     */
    public function createPayment(Payment $payment, PaymentContext $context): PaymentGatewayResult;

    /**
     * Verify payment status with the provider.
     */
    public function verifyPayment(Payment $payment): PaymentGatewayResult;

    /**
     * Refund a completed payment (full or partial amount).
     */
    public function refundPayment(Payment $payment, string $amount): PaymentGatewayResult;

    /**
     * Handle an inbound webhook payload from the provider.
     */
    public function handleWebhook(array $payload, array $headers): PaymentGatewayWebhookResult;
}
