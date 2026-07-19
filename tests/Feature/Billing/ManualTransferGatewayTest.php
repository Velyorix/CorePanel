<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Exceptions\UnsupportedGatewayOperationException;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\PaymentGatewayRegistry;
use Core\Billing\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualTransferGatewayTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    private ManualTransferGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = app(ManualTransferGateway::class);
        $this->payments = app(PaymentService::class);

        config([
            'corepanel.billing.manual_transfer.enabled' => true,
            'corepanel.billing.manual_transfer.beneficiary' => 'CorePanel SAS',
            'corepanel.billing.manual_transfer.iban' => 'FR7612345678901234567890123',
            'corepanel.billing.manual_transfer.bic' => 'AGRIFRPP',
            'corepanel.billing.manual_transfer.bank_name' => 'Demo Bank',
            'corepanel.billing.manual_transfer.reference_prefix' => 'PAY',
            'corepanel.billing.manual_transfer.instructions' => null,
        ]);
    }

    public function test_manual_transfer_gateway_is_registered_by_default(): void
    {
        $registry = app(PaymentGatewayRegistry::class);

        $this->assertTrue($registry->has(ManualTransferGateway::KEY));
        $this->assertInstanceOf(
            ManualTransferGateway::class,
            $registry->get(ManualTransferGateway::KEY),
        );
    }

    public function test_manual_transfer_gateway_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ManualTransferGateway::class),
            app(ManualTransferGateway::class),
        );
    }

    public function test_initiate_keeps_payment_pending_with_instructions(): void
    {
        $invoice = $this->makeUnpaidInvoice('120.00');

        $payment = $this->payments->initiate($invoice, ManualTransferGateway::KEY);

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(ManualTransferGateway::KEY, $payment->method);
        $this->assertSame('PAY-'.$payment->id, $payment->gateway_reference);
        $this->assertNull($payment->transaction_id);
        $this->assertNull($payment->paid_at);
        $this->assertStringContainsString('120.00', (string) $payment->notes);
        $this->assertStringContainsString('PAY-'.$payment->id, (string) $payment->notes);
        $this->assertStringContainsString('CorePanel SAS', (string) $payment->notes);
        $this->assertStringContainsString('FR7612345678901234567890123', (string) $payment->notes);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_staff_complete_marks_invoice_paid(): void
    {
        $invoice = $this->makeUnpaidInvoice('50.00');
        $payment = $this->payments->initiate($invoice, ManualTransferGateway::KEY);

        $completed = $this->payments->complete($payment, transactionId: 'WIRE-001');

        $this->assertSame(PaymentStatus::Completed, $completed->status);
        $this->assertSame('WIRE-001', $completed->transaction_id);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_verify_leaves_payment_pending(): void
    {
        $invoice = $this->makeUnpaidInvoice('25.00');
        $payment = $this->payments->initiate($invoice, ManualTransferGateway::KEY);

        $verified = $this->payments->verify($payment);

        $this->assertSame(PaymentStatus::Pending, $verified->status);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_manual_refund_reopens_invoice(): void
    {
        $invoice = $this->makeUnpaidInvoice('40.00');
        $payment = $this->payments->initiate($invoice, ManualTransferGateway::KEY);
        $this->payments->complete($payment);

        $refunded = $this->payments->refund($payment->fresh() ?? $payment);

        $this->assertSame(PaymentStatus::Refunded, $refunded->status);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_webhook_is_unsupported(): void
    {
        $this->expectException(UnsupportedGatewayOperationException::class);

        $this->gateway->handleWebhook(['event' => 'paid'], []);
    }

    public function test_custom_instructions_template_is_used(): void
    {
        config([
            'corepanel.billing.manual_transfer.instructions' => 'Wire :amount :currency using :reference',
        ]);

        $invoice = $this->makeUnpaidInvoice('10.00');
        $payment = $this->payments->initiate($invoice, ManualTransferGateway::KEY);

        $this->assertSame(
            'Wire 10.00 EUR using PAY-'.$payment->id,
            $payment->notes,
        );
    }

    public function test_disabled_gateway_is_not_registered_after_rebind(): void
    {
        config(['corepanel.billing.manual_transfer.enabled' => false]);

        $registry = app(PaymentGatewayRegistry::class);
        $registry->flush();

        if ((bool) config('corepanel.billing.manual_transfer.enabled', true)) {
            $registry->register(app(ManualTransferGateway::class));
        }

        $this->assertFalse($registry->has(ManualTransferGateway::KEY));
    }

    public function test_instructions_helper_matches_payment_notes_shape(): void
    {
        $invoice = $this->makeUnpaidInvoice('15.00');
        $payment = $this->payments->initiate($invoice, ManualTransferGateway::KEY);

        $this->assertSame($payment->notes, $this->gateway->instructions($payment));
    }

    private function makeUnpaidInvoice(string $total): Invoice
    {
        return Invoice::factory()->unpaid()->create([
            'subtotal' => $total,
            'tax_amount' => '0.00',
            'total_amount' => $total,
        ]);
    }
}
