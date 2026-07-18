<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Exceptions\InvalidPaymentException;
use Core\Billing\Exceptions\UnknownPaymentGatewayException;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Payment;
use Core\Billing\Services\PaymentGatewayRegistry;
use Core\Billing\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakePaymentGateway;
        app(PaymentGatewayRegistry::class)->register($this->gateway);
        $this->payments = app(PaymentService::class);
    }

    public function test_payment_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(PaymentService::class),
            app(PaymentService::class),
        );
    }

    public function test_payment_status_enum_covers_lifecycle(): void
    {
        $this->assertSame(
            ['pending', 'completed', 'failed', 'refunded', 'chargeback'],
            PaymentStatus::values(),
        );
        $this->assertTrue(PaymentStatus::Completed->isSuccessful());
        $this->assertTrue(PaymentStatus::Failed->isTerminal());
        $this->assertFalse(PaymentStatus::Pending->isTerminal());
    }

    public function test_initiate_creates_pending_payment_and_delegates_to_gateway(): void
    {
        $invoice = $this->makeUnpaidInvoice('100.00');

        $payment = $this->payments->initiate($invoice, 'fake');

        $this->assertSame(1, $this->gateway->createCalls);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('100.00', $payment->amount);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame($invoice->client_id, $payment->client_id);
        $this->assertSame('fake', $payment->method);
        $this->assertSame('fake-pending-'.$payment->id, $payment->transaction_id);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_initiate_rejects_draft_invoice(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'total_amount' => '50.00',
            'subtotal' => '50.00',
        ]);

        $this->expectException(InvalidPaymentException::class);

        $this->payments->initiate($invoice, 'fake');
    }

    public function test_initiate_rejects_cancelled_invoice(): void
    {
        $invoice = Invoice::factory()->cancelled()->create([
            'total_amount' => '50.00',
            'subtotal' => '50.00',
        ]);

        $this->expectException(InvalidPaymentException::class);

        $this->payments->initiate($invoice, 'fake');
    }

    public function test_initiate_rejects_amount_above_amount_due(): void
    {
        $invoice = $this->makeUnpaidInvoice('40.00');

        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Payment amount cannot exceed the invoice balance due.');

        $this->payments->initiate($invoice, 'fake', '40.01');
    }

    public function test_initiate_rejects_unknown_gateway(): void
    {
        $invoice = $this->makeUnpaidInvoice('10.00');

        $this->expectException(UnknownPaymentGatewayException::class);

        $this->payments->initiate($invoice, 'stripe');
    }

    public function test_initiate_defaults_amount_to_amount_due(): void
    {
        $invoice = $this->makeUnpaidInvoice('75.50');

        $payment = $this->payments->initiate($invoice, 'fake');

        $this->assertSame('75.50', $payment->amount);
    }

    public function test_complete_marks_payment_and_invoice_paid_when_full(): void
    {
        $invoice = $this->makeUnpaidInvoice('100.00');
        $payment = $this->payments->initiate($invoice, 'fake');

        $completed = $this->payments->complete($payment, 'txn-1', 'ref-1');

        $this->assertSame(PaymentStatus::Completed, $completed->status);
        $this->assertNotNull($completed->paid_at);
        $this->assertSame('txn-1', $completed->transaction_id);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
        $this->assertTrue($invoice->fresh()->isFullyPaid());
    }

    public function test_complete_supports_partial_payment_invoice_stays_unpaid(): void
    {
        $invoice = $this->makeUnpaidInvoice('100.00');
        $payment = $this->payments->initiate($invoice, 'fake', '40.00');

        $this->payments->complete($payment);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertSame('40.00', $invoice->amountPaid());
        $this->assertSame('60.00', $invoice->amountDue());
        $this->assertNull($invoice->paid_at);
    }

    public function test_complete_is_idempotent(): void
    {
        $invoice = $this->makeUnpaidInvoice('20.00');
        $payment = $this->payments->initiate($invoice, 'fake');
        $first = $this->payments->complete($payment, 'txn-a');
        $second = $this->payments->complete($first, 'txn-b');

        $this->assertTrue($first->is($second));
        $this->assertSame('txn-a', $second->transaction_id);
        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Completed)->count());
    }

    public function test_fail_marks_payment_failed_without_changing_invoice(): void
    {
        $invoice = $this->makeUnpaidInvoice('30.00');
        $payment = $this->payments->initiate($invoice, 'fake');

        $failed = $this->payments->fail($payment, 'Declined');

        $this->assertSame(PaymentStatus::Failed, $failed->status);
        $this->assertSame('Declined', $failed->notes);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->amountPaid());
    }

    public function test_verify_completes_when_gateway_returns_completed(): void
    {
        $invoice = $this->makeUnpaidInvoice('55.00');
        $payment = $this->payments->initiate($invoice, 'fake');

        $verified = $this->payments->verify($payment);

        $this->assertSame(1, $this->gateway->verifyCalls);
        $this->assertSame(PaymentStatus::Completed, $verified->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_initiate_can_complete_immediately_when_gateway_returns_completed(): void
    {
        $this->gateway->completingOnCreate();
        $invoice = $this->makeUnpaidInvoice('12.00');

        $payment = $this->payments->initiate($invoice, 'fake');

        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_refund_delegates_to_gateway_and_reopens_invoice(): void
    {
        $invoice = $this->makeUnpaidInvoice('80.00');
        $payment = $this->payments->initiate($invoice, 'fake');
        $this->payments->complete($payment);

        $refunded = $this->payments->refund($payment->fresh() ?? $payment);

        $this->assertSame(1, $this->gateway->refundCalls);
        $this->assertSame(PaymentStatus::Refunded, $refunded->status);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertSame('80.00', $invoice->fresh()->amountDue());
    }

    public function test_multiple_completed_payments_sum_to_paid(): void
    {
        $invoice = $this->makeUnpaidInvoice('100.00');

        $first = $this->payments->initiate($invoice, 'fake', '60.00');
        $this->payments->complete($first);

        $second = $this->payments->initiate($invoice->fresh() ?? $invoice, 'fake', '40.00');
        $this->payments->complete($second);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('100.00', $invoice->amountPaid());
        $this->assertSame('0.00', $invoice->amountDue());
        $this->assertCount(2, $this->payments->forInvoice($invoice));
    }

    public function test_payment_factory_persists_pending_row(): void
    {
        $payment = Payment::factory()->pending()->create([
            'amount' => '15.00',
        ]);

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('15.00', $payment->amount);
        $this->assertNotNull($payment->invoice);
        $this->assertSame($payment->invoice->client_id, $payment->client_id);
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
