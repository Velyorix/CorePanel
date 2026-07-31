<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Exceptions\InvalidPaymentException;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Payment;
use Core\Billing\Services\ClientCreditService;
use Core\Billing\Services\GatewayManager;
use Core\Billing\Services\PaymentService;
use Core\Clients\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

class PaymentCreditPriorityTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    private ClientCreditService $credits;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config(['corepanel.billing.client_credit.auto_apply_on_pay' => true]);

        $this->gateway = new FakePaymentGateway;
        $gateways = app(GatewayManager::class);
        $gateways->flush();
        $gateways->register($this->gateway);
        $gateways->enable('fake');

        $this->payments = app(PaymentService::class);
        $this->credits = app(ClientCreditService::class);
    }

    public function test_collect_pays_invoice_fully_from_credit_without_gateway(): void
    {
        $invoice = $this->makeUnpaidInvoice('40.00');
        $this->credits->add($invoice->client, '40.00');

        $result = $this->payments->collect($invoice, 'fake');

        $this->assertTrue($result->paidWithCreditOnly());
        $this->assertNotNull($result->creditPayment);
        $this->assertNull($result->gatewayPayment);
        $this->assertSame(0, $this->gateway->createCalls);
        $this->assertSame(PaymentStatus::Completed, $result->creditPayment->status);
        $this->assertSame(PaymentService::METHOD_CLIENT_CREDIT, $result->creditPayment->method);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('0.00', $this->credits->balance($invoice->client));
    }

    public function test_collect_applies_partial_credit_then_charges_gateway_remainder(): void
    {
        $invoice = $this->makeUnpaidInvoice('100.00');
        $this->credits->add($invoice->client, '30.00');

        $result = $this->payments->collect($invoice, 'fake');

        $this->assertTrue($result->creditApplied());
        $this->assertFalse($result->paidWithCreditOnly());
        $this->assertSame('30.00', $result->creditPayment?->amount);
        $this->assertSame('70.00', $result->gatewayPayment?->amount);
        $this->assertSame(1, $this->gateway->createCalls);
        $this->assertSame(PaymentStatus::Completed, $result->creditPayment?->status);
        $this->assertSame(PaymentStatus::Pending, $result->gatewayPayment?->status);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertSame('30.00', $invoice->fresh()->amountPaid());
        $this->assertSame('70.00', $invoice->fresh()->amountDue());
        $this->assertSame('0.00', $this->credits->balance($invoice->client));
    }

    public function test_collect_skips_credit_when_auto_apply_disabled(): void
    {
        config(['corepanel.billing.client_credit.auto_apply_on_pay' => false]);

        $invoice = $this->makeUnpaidInvoice('25.00');
        $this->credits->add($invoice->client, '25.00');

        $result = $this->payments->collect($invoice, 'fake');

        $this->assertFalse($result->creditApplied());
        $this->assertSame('25.00', $result->gatewayPayment?->amount);
        $this->assertSame(1, $this->gateway->createCalls);
        $this->assertSame('25.00', $this->credits->balance($invoice->client));
    }

    public function test_collect_with_no_credit_only_charges_gateway(): void
    {
        $invoice = $this->makeUnpaidInvoice('18.00');

        $result = $this->payments->collect($invoice, 'fake');

        $this->assertNull($result->creditPayment);
        $this->assertSame('18.00', $result->gatewayPayment?->amount);
        $this->assertSame(1, $this->gateway->createCalls);
    }

    public function test_collect_rejects_non_payable_invoice(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'total_amount' => '10.00',
            'subtotal' => '10.00',
        ]);

        $this->expectException(InvalidPaymentException::class);

        $this->payments->collect($invoice, 'fake');
    }

    public function test_initiate_still_bypasses_credit_application(): void
    {
        $invoice = $this->makeUnpaidInvoice('50.00');
        $this->credits->add($invoice->client, '50.00');

        $payment = $this->payments->initiate($invoice, 'fake');

        $this->assertSame('fake', $payment->method);
        $this->assertSame('50.00', $payment->amount);
        $this->assertSame(1, $this->gateway->createCalls);
        $this->assertSame('50.00', $this->credits->balance($invoice->client));
        $this->assertSame(0, Payment::query()->where('method', PaymentService::METHOD_CLIENT_CREDIT)->count());
    }

    public function test_partial_credit_then_gateway_completion_marks_invoice_paid(): void
    {
        $invoice = $this->makeUnpaidInvoice('80.00');
        $this->credits->add($invoice->client, '20.00');

        $result = $this->payments->collect($invoice, 'fake');
        $this->payments->complete($result->gatewayPayment);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->amountDue());
        $this->assertSame(2, Payment::query()->where('status', PaymentStatus::Completed)->count());
    }

    private function makeUnpaidInvoice(string $total): Invoice
    {
        $client = Client::factory()->create(['credit_balance' => '0.00']);

        return Invoice::factory()->unpaid()->create([
            'client_id' => $client->id,
            'subtotal' => $total,
            'tax_amount' => '0.00',
            'total_amount' => $total,
        ]);
    }
}
