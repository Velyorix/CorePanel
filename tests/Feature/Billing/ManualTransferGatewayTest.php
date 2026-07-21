<?php

namespace Tests\Feature\Billing;

use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Exceptions\UnsupportedGatewayOperationException;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\PaymentGateway;
use Core\Billing\Services\GatewayManager;
use Core\Billing\Services\PaymentService;
use Core\Providers\Contracts\PaymentGatewayInterface;
use Core\Providers\Services\ProviderRegistry;
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
        $gateways = app(GatewayManager::class);

        $this->assertTrue($gateways->has(ManualTransferGateway::KEY));
        $this->assertTrue($gateways->isEnabled(ManualTransferGateway::KEY));
        $this->assertInstanceOf(
            ManualTransferGateway::class,
            $gateways->resolve(ManualTransferGateway::KEY),
        );
    }

    public function test_manual_transfer_is_registered_on_provider_registry(): void
    {
        $registry = app(ProviderRegistry::class);

        $this->assertTrue($registry->hasPaymentGateway(ManualTransferGateway::KEY));
        $this->assertInstanceOf(
            ManualTransferGateway::class,
            $registry->paymentGateway(ManualTransferGateway::KEY),
        );
        $this->assertInstanceOf(
            PaymentGatewayInterface::class,
            $registry->paymentGateway(ManualTransferGateway::KEY),
        );
    }

    public function test_manual_transfer_gateway_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ManualTransferGateway::class),
            app(ManualTransferGateway::class),
        );
    }

    public function test_default_label_covers_transfer_and_cheque(): void
    {
        config(['corepanel.billing.manual_transfer.label' => null]);

        $this->assertSame('Bank transfer / cheque', $this->gateway->label());
    }

    public function test_config_label_overrides_default(): void
    {
        config(['corepanel.billing.manual_transfer.label' => 'Wire / cheque']);

        $this->assertSame('Wire / cheque', $this->gateway->label());
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
        $this->assertStringContainsString('cheque', strtolower((string) $payment->notes));
        $this->assertStringContainsString('CorePanel SAS', (string) $payment->notes);
        $this->assertStringContainsString('FR7612345678901234567890123', (string) $payment->notes);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_empty_reference_prefix_falls_back_to_pay(): void
    {
        config(['corepanel.billing.manual_transfer.reference_prefix' => '']);

        $invoice = $this->makeUnpaidInvoice('5.00');
        $payment = $this->payments->initiate($invoice, ManualTransferGateway::KEY);

        $this->assertSame('PAY-'.$payment->id, $payment->gateway_reference);
    }

    public function test_provider_interface_aliases_match_billing_contract(): void
    {
        $invoice = $this->makeUnpaidInvoice('30.00');
        $payment = $this->payments->initiate($invoice, ManualTransferGateway::KEY);

        $charged = $this->gateway->charge($payment, new PaymentContext);
        $validated = $this->gateway->validate($payment);

        $this->assertSame(PaymentStatus::Pending, $charged->status);
        $this->assertSame(PaymentStatus::Pending, $validated->status);
        $this->assertSame($payment->gateway_reference, $charged->gatewayReference);
        $this->assertStringContainsString('Awaiting manual confirmation', (string) $validated->message);

        $this->payments->complete($payment);
        $refunded = $this->gateway->refund($payment->fresh() ?? $payment, '30.00');

        $this->assertSame(PaymentStatus::Refunded, $refunded->status);
        $this->assertStringContainsString('30.00', (string) $refunded->message);
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

    public function test_config_disabled_skips_auto_enable_on_first_install(): void
    {
        PaymentGateway::query()->where('key', ManualTransferGateway::KEY)->delete();
        config(['corepanel.billing.manual_transfer.enabled' => false]);

        $gateways = app(GatewayManager::class);
        $gateways->flush();
        $gateways->register(app(ManualTransferGateway::class));
        $gateways->sync();

        $this->assertTrue($gateways->has(ManualTransferGateway::KEY));
        $this->assertFalse($gateways->isEnabled(ManualTransferGateway::KEY));
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
