<?php

namespace Core\Billing\Gateways;

use Core\Billing\Contracts\PaymentGateway;
use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\DataTransferObjects\PaymentGatewayWebhookResult;
use Core\Billing\Exceptions\UnsupportedGatewayOperationException;
use Core\Billing\Models\Payment;

/**
 * Native offline gateway: bank transfer / cheque, pending until staff confirms.
 */
class ManualTransferGateway implements PaymentGateway
{
    public const KEY = 'manual_transfer';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        $label = config('corepanel.billing.manual_transfer.label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return (string) __('Bank transfer');
    }

    public function createPayment(Payment $payment, PaymentContext $context): PaymentGatewayResult
    {
        $reference = $this->paymentReference($payment);

        return PaymentGatewayResult::pending(
            transactionId: null,
            gatewayReference: $reference,
            message: $this->instructionsMessage($payment, $reference),
        );
    }

    public function verifyPayment(Payment $payment): PaymentGatewayResult
    {
        return PaymentGatewayResult::pending(
            transactionId: $payment->transaction_id,
            gatewayReference: $payment->gateway_reference ?? $this->paymentReference($payment),
            message: (string) __('Awaiting manual confirmation.'),
        );
    }

    public function refundPayment(Payment $payment, string $amount): PaymentGatewayResult
    {
        return PaymentGatewayResult::refunded(
            transactionId: $payment->transaction_id,
            gatewayReference: $payment->gateway_reference,
            message: (string) __('Manual refund of :amount recorded.', ['amount' => $amount]),
        );
    }

    public function handleWebhook(array $payload, array $headers): PaymentGatewayWebhookResult
    {
        throw UnsupportedGatewayOperationException::forOperation($this->key(), 'webhook');
    }

    /**
     * Human-readable payment instructions for the client.
     */
    public function instructions(Payment $payment): string
    {
        return $this->instructionsMessage(
            $payment,
            $payment->gateway_reference ?? $this->paymentReference($payment),
        );
    }

    private function paymentReference(Payment $payment): string
    {
        $prefix = trim((string) config('corepanel.billing.manual_transfer.reference_prefix', 'PAY'));

        if ($prefix === '') {
            $prefix = 'PAY';
        }

        return strtoupper($prefix).'-'.$payment->id;
    }

    private function instructionsMessage(Payment $payment, string $reference): string
    {
        $configured = config('corepanel.billing.manual_transfer.instructions');

        if (is_string($configured) && trim($configured) !== '') {
            return str_replace(
                [':amount', ':currency', ':reference'],
                [$payment->amount, $payment->currency, $reference],
                $configured,
            );
        }

        $lines = [
            __('Please pay :amount :currency by bank transfer or cheque.', [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]),
            __('Payment reference: :reference', ['reference' => $reference]),
        ];

        $beneficiary = $this->configString('beneficiary');
        $iban = $this->configString('iban');
        $bic = $this->configString('bic');
        $bankName = $this->configString('bank_name');

        if ($beneficiary !== null) {
            $lines[] = __('Beneficiary: :value', ['value' => $beneficiary]);
        }

        if ($iban !== null) {
            $lines[] = __('IBAN: :value', ['value' => $iban]);
        }

        if ($bic !== null) {
            $lines[] = __('BIC: :value', ['value' => $bic]);
        }

        if ($bankName !== null) {
            $lines[] = __('Bank: :value', ['value' => $bankName]);
        }

        $lines[] = __('Your invoice will be marked paid once the payment is confirmed.');

        return implode("\n", $lines);
    }

    private function configString(string $key): ?string
    {
        $value = config('corepanel.billing.manual_transfer.'.$key);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
