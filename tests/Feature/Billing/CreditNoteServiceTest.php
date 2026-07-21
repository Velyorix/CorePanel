<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\ClientCreditTransactionType;
use Core\Billing\Enums\CreditNoteSettlement;
use Core\Billing\Enums\CreditNoteStatus;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Exceptions\InvalidCreditNoteException;
use Core\Billing\Models\ClientCreditTransaction;
use Core\Billing\Models\CreditNote;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Payment;
use Core\Billing\Services\ClientCreditService;
use Core\Billing\Services\CreditNoteNumberService;
use Core\Billing\Services\CreditNoteService;
use Core\Billing\Services\GatewayManager;
use Core\Billing\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

class CreditNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreditNoteService $creditNotes;

    private PaymentService $payments;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakePaymentGateway;
        $gateways = app(GatewayManager::class);
        $gateways->flush();
        $gateways->register($this->gateway);
        $gateways->enable('fake');

        $this->payments = app(PaymentService::class);
        $this->creditNotes = app(CreditNoteService::class);
    }

    public function test_services_are_registered_as_singletons(): void
    {
        $this->assertSame(app(CreditNoteService::class), app(CreditNoteService::class));
        $this->assertSame(app(CreditNoteNumberService::class), app(CreditNoteNumberService::class));
    }

    public function test_enums_cover_status_and_settlement(): void
    {
        $this->assertSame(['draft', 'issued', 'cancelled'], CreditNoteStatus::values());
        $this->assertSame(['wallet', 'payment_refund'], CreditNoteSettlement::values());
        $this->assertTrue(CreditNoteStatus::Draft->isEditable());
        $this->assertTrue(CreditNoteStatus::Issued->isIssued());
    }

    public function test_create_draft_from_paid_invoice(): void
    {
        $invoice = $this->makePaidInvoice('100.00');

        $note = $this->creditNotes->createFromInvoice(
            $invoice,
            '40.00',
            reason: 'Partial goodwill credit',
        );

        $this->assertSame(CreditNoteStatus::Draft, $note->status);
        $this->assertNull($note->credit_note_number);
        $this->assertSame('40.00', $note->amount);
        $this->assertSame($invoice->id, $note->invoice_id);
        $this->assertSame($invoice->client_id, $note->client_id);
        $this->assertSame('Partial goodwill credit', $note->reason);
        $this->assertTrue($invoice->creditNotes()->whereKey($note->id)->exists());
    }

    public function test_issue_to_wallet_credits_client_and_numbers_note(): void
    {
        $invoice = $this->makePaidInvoice('100.00');
        $note = $this->creditNotes->createFromInvoice($invoice, '25.00', reason: 'Service credit');

        $issued = $this->creditNotes->issueToWallet($note);

        $this->assertSame(CreditNoteStatus::Issued, $issued->status);
        $this->assertSame(CreditNoteSettlement::Wallet, $issued->settlement);
        $this->assertNotNull($issued->credit_note_number);
        $this->assertStringStartsWith('CN-', $issued->credit_note_number);
        $this->assertNotNull($issued->issued_at);
        $this->assertSame('25.00', app(ClientCreditService::class)->balance($invoice->client));
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $txn = ClientCreditTransaction::query()->firstOrFail();
        $this->assertSame(ClientCreditTransactionType::Refund, $txn->type);
        $this->assertSame('credit_note:'.$issued->id, $txn->reference);
    }

    public function test_full_wallet_credit_marks_invoice_refunded(): void
    {
        $invoice = $this->makePaidInvoice('80.00');
        $note = $this->creditNotes->createFromInvoice($invoice, '80.00');

        $this->creditNotes->issueToWallet($note);

        $this->assertSame(InvoiceStatus::Refunded, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertSame('80.00', $this->creditNotes->amountIssuedForInvoice($invoice));
    }

    public function test_issue_with_payment_refund_uses_gateway(): void
    {
        $invoice = Invoice::factory()->unpaid()->create([
            'subtotal' => '100.00',
            'tax_amount' => '0.00',
            'total_amount' => '100.00',
            'discount_amount' => '0.00',
        ]);
        $payment = $this->payments->initiate($invoice, 'fake');
        $this->payments->complete($payment);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $note = $this->creditNotes->createFromInvoice($invoice->fresh(), '100.00', reason: 'Full refund');
        $issued = $this->creditNotes->issueWithPaymentRefund($note, $payment->fresh());

        $this->assertSame(CreditNoteStatus::Issued, $issued->status);
        $this->assertSame(CreditNoteSettlement::PaymentRefund, $issued->settlement);
        $this->assertSame($payment->id, $issued->payment_id);
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(1, $this->gateway->refundCalls);
        $this->assertSame(InvoiceStatus::Refunded, $invoice->fresh()->status);
    }

    public function test_rejects_amount_above_remaining_creditable(): void
    {
        $invoice = $this->makePaidInvoice('50.00');
        $this->creditNotes->createFromInvoice($invoice, '30.00');

        $this->expectException(InvalidCreditNoteException::class);

        $this->creditNotes->createFromInvoice($invoice, '25.00');
    }

    public function test_rejects_credit_note_on_draft_invoice(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'total_amount' => '20.00',
            'subtotal' => '20.00',
        ]);

        $this->expectException(InvalidCreditNoteException::class);

        $this->creditNotes->createFromInvoice($invoice, '10.00');
    }

    public function test_cancel_draft_only(): void
    {
        $invoice = $this->makePaidInvoice('40.00');
        $note = $this->creditNotes->createFromInvoice($invoice, '10.00');

        $cancelled = $this->creditNotes->cancel($note);

        $this->assertSame(CreditNoteStatus::Cancelled, $cancelled->status);

        $issued = $this->creditNotes->createFromInvoice($invoice, '10.00');
        $this->creditNotes->issueToWallet($issued);

        $this->expectException(InvalidCreditNoteException::class);
        $this->creditNotes->cancel($issued->fresh());
    }

    public function test_wallet_credit_is_idempotent_via_key(): void
    {
        $invoice = $this->makePaidInvoice('15.00');
        $note = $this->creditNotes->createFromInvoice($invoice, '15.00');
        $this->creditNotes->issueToWallet($note);

        $this->assertSame(1, ClientCreditTransaction::query()->count());
        $this->assertSame('15.00', app(ClientCreditService::class)->balance($invoice->client));
    }

    public function test_factory_persists_draft_credit_note(): void
    {
        $note = CreditNote::factory()->draft()->create(['amount' => '12.50']);

        $this->assertSame(CreditNoteStatus::Draft, $note->status);
        $this->assertSame('12.50', $note->amount);
        $this->assertNotNull($note->invoice);
        $this->assertSame($note->invoice->client_id, $note->client_id);
    }

    private function makePaidInvoice(string $total): Invoice
    {
        return Invoice::factory()->paid()->create([
            'subtotal' => $total,
            'tax_amount' => '0.00',
            'total_amount' => $total,
            'discount_amount' => '0.00',
        ]);
    }
}
