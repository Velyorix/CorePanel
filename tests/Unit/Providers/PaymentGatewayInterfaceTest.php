<?php

namespace Tests\Unit\Providers;

use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\Models\Payment;
use Core\Providers\Contracts\PaymentGatewayInterface;
use PHPUnit\Framework\TestCase;

class PaymentGatewayInterfaceTest extends TestCase
{
    public function test_contract_can_be_implemented(): void
    {
        $payment = new Payment([
            'id' => 42,
            'amount' => '19.99',
            'currency' => 'EUR',
        ]);

        $context = new PaymentContext(returnUrl: 'https://example.test/return');

        $gateway = new class implements PaymentGatewayInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Stub Gateway';
            }

            public function charge(Payment $payment, PaymentContext $context): PaymentGatewayResult
            {
                return PaymentGatewayResult::pending(
                    transactionId: 'txn-'.$payment->id,
                    gatewayReference: 'ref-'.$payment->id,
                    message: 'Charged via '.$context->returnUrl,
                );
            }

            public function refund(Payment $payment, string $amount): PaymentGatewayResult
            {
                return PaymentGatewayResult::refunded(
                    transactionId: 'refund-'.$payment->id,
                    gatewayReference: 'ref-'.$payment->id,
                    message: "Refunded {$amount}",
                );
            }

            public function validate(Payment $payment): PaymentGatewayResult
            {
                return PaymentGatewayResult::completed(
                    transactionId: 'txn-'.$payment->id,
                    gatewayReference: 'ref-'.$payment->id,
                );
            }
        };

        $this->assertSame('stub', $gateway->key());
        $this->assertSame('Stub Gateway', $gateway->label());
        $this->assertSame('pending', $gateway->charge($payment, $context)->status->value);
        $this->assertSame('refunded', $gateway->refund($payment, '5.00')->status->value);
        $this->assertSame('completed', $gateway->validate($payment)->status->value);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }
}
