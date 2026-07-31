<?php

namespace Tests\Support\Billing;

use Core\Billing\Contracts\PaymentGateway;
use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\DataTransferObjects\PaymentGatewayWebhookResult;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Exceptions\InvalidPaymentWebhookException;
use Core\Billing\Exceptions\UnsupportedGatewayOperationException;
use Core\Billing\Models\Payment;

/**
 * Test double payment gateway with configurable create/verify/refund/webhook behavior.
 */
class FakePaymentGateway implements PaymentGateway
{
    public int $createCalls = 0;

    public int $verifyCalls = 0;

    public int $refundCalls = 0;

    public int $webhookCalls = 0;

    public function __construct(
        private string $key = 'fake',
        private string $label = 'Fake Gateway',
        private PaymentStatus $createStatus = PaymentStatus::Pending,
        private PaymentStatus $verifyStatus = PaymentStatus::Completed,
        private bool $supportRefund = true,
        private bool $supportWebhook = false,
        private ?string $expectedSignature = null,
        private PaymentStatus $webhookStatus = PaymentStatus::Completed,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function createPayment(Payment $payment, PaymentContext $context): PaymentGatewayResult
    {
        $this->createCalls++;

        return match ($this->createStatus) {
            PaymentStatus::Completed => PaymentGatewayResult::completed(
                transactionId: 'fake-txn-'.$payment->id,
                gatewayReference: 'fake-ref-'.$payment->id,
            ),
            PaymentStatus::Failed => PaymentGatewayResult::failed('Fake gateway declined the payment.'),
            default => PaymentGatewayResult::pending(
                transactionId: 'fake-pending-'.$payment->id,
                gatewayReference: 'fake-ref-'.$payment->id,
                message: 'Awaiting confirmation',
            ),
        };
    }

    public function verifyPayment(Payment $payment): PaymentGatewayResult
    {
        $this->verifyCalls++;

        return match ($this->verifyStatus) {
            PaymentStatus::Completed => PaymentGatewayResult::completed(
                transactionId: $payment->transaction_id ?? 'fake-verified-'.$payment->id,
                gatewayReference: $payment->gateway_reference ?? 'fake-ref-'.$payment->id,
            ),
            PaymentStatus::Failed => PaymentGatewayResult::failed('Fake verification failed.'),
            default => PaymentGatewayResult::pending(
                transactionId: $payment->transaction_id,
                gatewayReference: $payment->gateway_reference,
            ),
        };
    }

    public function refundPayment(Payment $payment, string $amount): PaymentGatewayResult
    {
        $this->refundCalls++;

        if (! $this->supportRefund) {
            throw UnsupportedGatewayOperationException::forOperation($this->key, 'refund');
        }

        return PaymentGatewayResult::refunded(
            transactionId: 'fake-refund-'.$payment->id,
            gatewayReference: $payment->gateway_reference,
            message: "Refunded {$amount}",
        );
    }

    public function handleWebhook(array $payload, array $headers): PaymentGatewayWebhookResult
    {
        $this->webhookCalls++;

        if (! $this->supportWebhook) {
            throw UnsupportedGatewayOperationException::forOperation($this->key, 'webhook');
        }

        if ($this->expectedSignature !== null) {
            $provided = $headers['x-signature'] ?? null;

            if (! is_string($provided) || ! hash_equals($this->expectedSignature, $provided)) {
                throw InvalidPaymentWebhookException::invalidSignature($this->key);
            }
        }

        return new PaymentGatewayWebhookResult(
            paymentId: isset($payload['payment_id']) ? (int) $payload['payment_id'] : null,
            status: $this->webhookStatus,
            transactionId: isset($payload['transaction_id']) && is_string($payload['transaction_id'])
                ? $payload['transaction_id']
                : null,
            gatewayReference: isset($payload['gateway_reference']) && is_string($payload['gateway_reference'])
                ? $payload['gateway_reference']
                : null,
            raw: $payload,
        );
    }

    public function completingOnCreate(): self
    {
        $this->createStatus = PaymentStatus::Completed;

        return $this;
    }

    public function failingOnCreate(): self
    {
        $this->createStatus = PaymentStatus::Failed;

        return $this;
    }

    public function withWebhookSupport(?string $expectedSignature = 'valid-signature'): self
    {
        $this->supportWebhook = true;
        $this->expectedSignature = $expectedSignature;

        return $this;
    }

    public function webhookCompletes(): self
    {
        $this->webhookStatus = PaymentStatus::Completed;

        return $this;
    }

    public function webhookFails(): self
    {
        $this->webhookStatus = PaymentStatus::Failed;

        return $this;
    }
}
