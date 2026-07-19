<?php

namespace Tests\Feature\Billing;

use Core\Billing\Contracts\OverdueServiceActions;
use Core\Billing\DataTransferObjects\CreateQuoteInput;
use Core\Billing\DataTransferObjects\QuoteLineInput;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Events\ClientCreditAdded;
use Core\Billing\Events\CreditNoteIssued;
use Core\Billing\Events\InvoiceIssued;
use Core\Billing\Events\InvoicePaid;
use Core\Billing\Events\InvoiceServiceSuspended;
use Core\Billing\Events\PaymentCompleted;
use Core\Billing\Events\PaymentFailed;
use Core\Billing\Events\PaymentRefunded;
use Core\Billing\Events\QuoteAccepted;
use Core\Billing\Events\QuoteConverted;
use Core\Billing\Events\QuoteSent;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Billing\Models\Payment;
use Core\Billing\Services\BillingAuditLogger;
use Core\Billing\Services\ClientCreditService;
use Core\Billing\Services\CreditNoteService;
use Core\Billing\Services\InvoiceService;
use Core\Billing\Services\OverdueSuspensionService;
use Core\Billing\Services\PaymentGatewayRegistry;
use Core\Billing\Services\PaymentService;
use Core\Billing\Services\QuoteService;
use Core\Clients\Models\Client;
use Core\Products\Enums\BillingCycle;
use Core\Support\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

class BillingAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.billing.audit.enabled' => true,
            'corepanel.billing.quote_numbering.prefix' => 'QUO',
            'corepanel.billing.quote_numbering.padding' => 6,
            'corepanel.billing.quote_numbering.include_year' => true,
            'corepanel.billing.invoice_numbering.prefix' => 'INV',
            'corepanel.billing.invoice_numbering.padding' => 6,
            'corepanel.billing.invoice_numbering.include_year' => true,
            'corepanel.billing.quote_valid_days' => 30,
            'corepanel.billing.invoice_due_days' => 14,
            'corepanel.billing.suspension.enabled' => true,
            'corepanel.billing.suspension.suspend_after_days' => 3,
            'corepanel.billing.suspension.terminate_after_days' => 7,
            'corepanel.billing.suspension.skip_if_credit_available' => true,
            'corepanel.billing.suspension.skip_vip_clients' => true,
        ]);

        $registry = app(PaymentGatewayRegistry::class);
        $registry->flush();
        $registry->register(new FakePaymentGateway);
    }

    public function test_invoice_issue_creates_audit_and_dispatches_event(): void
    {
        Event::fake([InvoiceIssued::class]);

        $invoice = Invoice::factory()->draft()->create([
            'subtotal' => '50.00',
            'total_amount' => '50.00',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'line_total' => '50.00',
            'unit_price' => '50.00',
            'setup_fee' => '0.00',
            'tax_amount' => '0.00',
        ]);

        $issued = app(InvoiceService::class)->issue($invoice);

        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_INVOICE_ISSUED,
            'entity_type' => Invoice::class,
            'entity_id' => $issued->id,
        ]);

        $log = AuditLog::query()
            ->where('action', BillingAuditLogger::ACTION_INVOICE_ISSUED)
            ->where('entity_id', $issued->id)
            ->firstOrFail();
        $this->assertSame('draft', $log->before['status']);
        $this->assertSame('unpaid', $log->after['status']);
        $this->assertSame($issued->client_id, $log->after['client_id']);
        $this->assertSame('50.00', $log->after['amount']);

        Event::assertDispatched(
            InvoiceIssued::class,
            fn (InvoiceIssued $event): bool => $event->invoice->id === $issued->id,
        );
    }

    public function test_payment_complete_creates_audit_and_dispatches_invoice_paid_when_fully_paid(): void
    {
        Event::fake([PaymentCompleted::class, InvoicePaid::class]);

        $invoice = $this->makeUnpaidInvoice('100.00');
        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'amount' => '100.00',
        ]);

        $completed = app(PaymentService::class)->complete($payment, 'txn-1');

        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_PAYMENT_COMPLETED,
            'entity_type' => Payment::class,
            'entity_id' => $completed->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_INVOICE_PAID,
            'entity_type' => Invoice::class,
            'entity_id' => $invoice->id,
        ]);

        Event::assertDispatched(
            PaymentCompleted::class,
            fn (PaymentCompleted $event): bool => $event->payment->id === $completed->id,
        );
        Event::assertDispatched(
            InvoicePaid::class,
            fn (InvoicePaid $event): bool => $event->invoice->id === $invoice->id,
        );
    }

    public function test_payment_complete_partial_does_not_dispatch_invoice_paid(): void
    {
        Event::fake([PaymentCompleted::class, InvoicePaid::class]);

        $invoice = $this->makeUnpaidInvoice('100.00');
        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'amount' => '40.00',
        ]);

        app(PaymentService::class)->complete($payment);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => BillingAuditLogger::ACTION_INVOICE_PAID,
        ]);
        Event::assertNotDispatched(InvoicePaid::class);
        Event::assertDispatched(PaymentCompleted::class);
    }

    public function test_payment_complete_is_not_double_audited_when_already_completed(): void
    {
        $invoice = $this->makeUnpaidInvoice('20.00');
        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'amount' => '20.00',
        ]);

        app(PaymentService::class)->complete($payment, 'txn-a');
        app(PaymentService::class)->complete($payment->fresh() ?? $payment, 'txn-b');

        $this->assertSame(1, AuditLog::query()
            ->where('action', BillingAuditLogger::ACTION_PAYMENT_COMPLETED)
            ->where('entity_id', $payment->id)
            ->count());
    }

    public function test_payment_fail_audits(): void
    {
        Event::fake([PaymentFailed::class]);

        $invoice = $this->makeUnpaidInvoice('30.00');
        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'amount' => '30.00',
        ]);

        $failed = app(PaymentService::class)->fail($payment, 'Declined');

        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_PAYMENT_FAILED,
            'entity_type' => Payment::class,
            'entity_id' => $failed->id,
        ]);

        Event::assertDispatched(
            PaymentFailed::class,
            fn (PaymentFailed $event): bool => $event->payment->id === $failed->id,
        );
    }

    public function test_payment_fail_is_not_double_audited_when_already_failed(): void
    {
        $invoice = $this->makeUnpaidInvoice('30.00');
        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'amount' => '30.00',
        ]);

        app(PaymentService::class)->fail($payment);
        app(PaymentService::class)->fail($payment->fresh() ?? $payment);

        $this->assertSame(1, AuditLog::query()
            ->where('action', BillingAuditLogger::ACTION_PAYMENT_FAILED)
            ->where('entity_id', $payment->id)
            ->count());
    }

    public function test_payment_refund_audits_with_refund_amount_and_does_not_fire_invoice_paid(): void
    {
        $invoice = $this->makeUnpaidInvoice('80.00');
        $payment = app(PaymentService::class)->initiate($invoice, 'fake');
        app(PaymentService::class)->complete($payment);

        Event::fake([PaymentRefunded::class, InvoicePaid::class]);

        $refunded = app(PaymentService::class)->refund($payment->fresh() ?? $payment);

        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_PAYMENT_REFUNDED,
            'entity_type' => Payment::class,
            'entity_id' => $refunded->id,
        ]);

        $log = AuditLog::query()
            ->where('action', BillingAuditLogger::ACTION_PAYMENT_REFUNDED)
            ->where('entity_id', $refunded->id)
            ->firstOrFail();
        $this->assertSame('80.00', $log->after['refund_amount']);

        Event::assertDispatched(PaymentRefunded::class);
        Event::assertNotDispatched(InvoicePaid::class);
    }

    public function test_quote_send_accept_convert_audit_and_events(): void
    {
        Event::fake([QuoteSent::class, QuoteAccepted::class, QuoteConverted::class]);

        $client = Client::factory()->create([
            'country' => 'FR',
            'company_name' => null,
            'vat_number' => null,
        ]);

        $quote = app(QuoteService::class)->create($this->quoteInput($client, '100.00'));

        $sent = app(QuoteService::class)->send($quote);
        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_QUOTE_SENT,
            'entity_id' => $sent->id,
        ]);
        Event::assertDispatched(
            QuoteSent::class,
            fn (QuoteSent $event): bool => $event->quote->id === $sent->id,
        );

        $accepted = app(QuoteService::class)->accept($sent);
        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_QUOTE_ACCEPTED,
            'entity_id' => $accepted->id,
        ]);
        Event::assertDispatched(
            QuoteAccepted::class,
            fn (QuoteAccepted $event): bool => $event->quote->id === $accepted->id,
        );

        $invoice = app(QuoteService::class)->convertToInvoice($accepted);
        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_QUOTE_CONVERTED,
            'entity_id' => $accepted->id,
        ]);

        $log = AuditLog::query()
            ->where('action', BillingAuditLogger::ACTION_QUOTE_CONVERTED)
            ->where('entity_id', $accepted->id)
            ->firstOrFail();
        $this->assertSame($invoice->id, $log->after['invoice_id']);

        Event::assertDispatched(
            QuoteConverted::class,
            fn (QuoteConverted $event): bool => $event->quote->id === $accepted->id
                && $event->invoice->id === $invoice->id,
        );

        // Idempotent re-conversion must not create a second audit row or event.
        app(QuoteService::class)->convertToInvoice($accepted->fresh() ?? $accepted);

        $this->assertSame(1, AuditLog::query()
            ->where('action', BillingAuditLogger::ACTION_QUOTE_CONVERTED)
            ->where('entity_id', $accepted->id)
            ->count());
        Event::assertDispatched(QuoteConverted::class, 1);
    }

    public function test_credit_note_issue_to_wallet_audits_and_dispatches_event(): void
    {
        Event::fake([CreditNoteIssued::class]);

        $invoice = Invoice::factory()->paid()->create([
            'subtotal' => '100.00',
            'tax_amount' => '0.00',
            'total_amount' => '100.00',
        ]);
        $note = app(CreditNoteService::class)->createFromInvoice($invoice, '25.00', reason: 'Service credit');

        $issued = app(CreditNoteService::class)->issueToWallet($note);

        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_CREDIT_NOTE_ISSUED,
            'entity_type' => \Core\Billing\Models\CreditNote::class,
            'entity_id' => $issued->id,
        ]);

        Event::assertDispatched(
            CreditNoteIssued::class,
            fn (CreditNoteIssued $event): bool => $event->creditNote->id === $issued->id,
        );
    }

    public function test_client_credit_add_audits_and_dispatches_event(): void
    {
        Event::fake([ClientCreditAdded::class]);

        $client = Client::factory()->create();

        $transaction = app(ClientCreditService::class)->add($client, '10.00', description: 'Manual top-up');

        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_CLIENT_CREDIT_ADDED,
            'entity_type' => \Core\Billing\Models\ClientCreditTransaction::class,
            'entity_id' => $transaction->id,
        ]);

        Event::assertDispatched(
            ClientCreditAdded::class,
            fn (ClientCreditAdded $event): bool => $event->transaction->id === $transaction->id,
        );
    }

    public function test_client_credit_deduct_does_not_audit(): void
    {
        Event::fake([ClientCreditAdded::class]);

        $client = Client::factory()->create();
        app(ClientCreditService::class)->add($client, '50.00');
        app(ClientCreditService::class)->deduct($client, '20.00');

        $this->assertSame(1, AuditLog::query()
            ->where('action', BillingAuditLogger::ACTION_CLIENT_CREDIT_ADDED)
            ->count());
        Event::assertDispatched(ClientCreditAdded::class, 1);
    }

    public function test_client_credit_idempotent_add_does_not_double_audit(): void
    {
        $client = Client::factory()->create();

        app(ClientCreditService::class)->add($client, '25.00', idempotencyKey: 'topup-001');
        app(ClientCreditService::class)->add($client, '25.00', idempotencyKey: 'topup-001');

        $this->assertSame(1, AuditLog::query()
            ->where('action', BillingAuditLogger::ACTION_CLIENT_CREDIT_ADDED)
            ->count());
    }

    public function test_overdue_suspension_audits_and_dispatches_event(): void
    {
        Event::fake([InvoiceServiceSuspended::class]);

        $fake = new class implements OverdueServiceActions
        {
            public function suspend(Invoice $invoice, int $serviceId, string $reason): void
            {
            }

            public function terminate(Invoice $invoice, int $serviceId, string $reason): void
            {
            }
        };
        $this->app->instance(OverdueServiceActions::class, $fake);

        $invoice = Invoice::factory()->overdue()->create([
            'client_id' => Client::factory()->create(['credit_balance' => '0.00'])->id,
            'total_amount' => '50.00',
            'subtotal' => '50.00',
            'due_at' => Carbon::parse('2026-01-01'),
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'service_id' => 42,
            'line_total' => '50.00',
            'unit_price' => '50.00',
            'setup_fee' => '0.00',
            'tax_amount' => '0.00',
        ]);

        app(OverdueSuspensionService::class)->process(Carbon::parse('2026-01-04'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => BillingAuditLogger::ACTION_INVOICE_SERVICE_SUSPENDED,
            'entity_type' => Invoice::class,
            'entity_id' => $invoice->id,
        ]);

        $log = AuditLog::query()
            ->where('action', BillingAuditLogger::ACTION_INVOICE_SERVICE_SUSPENDED)
            ->where('entity_id', $invoice->id)
            ->firstOrFail();
        $this->assertSame([42], $log->after['service_ids']);
        $this->assertSame('suspended', $log->after['overdue_action']);

        Event::assertDispatched(
            InvoiceServiceSuspended::class,
            fn (InvoiceServiceSuspended $event): bool => $event->invoice->id === $invoice->id
                && $event->serviceIds === [42],
        );
    }

    public function test_disabled_config_writes_no_audit_rows(): void
    {
        config(['corepanel.billing.audit.enabled' => false]);

        $invoice = Invoice::factory()->draft()->create([
            'subtotal' => '50.00',
            'total_amount' => '50.00',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'line_total' => '50.00',
            'unit_price' => '50.00',
            'setup_fee' => '0.00',
            'tax_amount' => '0.00',
        ]);

        app(InvoiceService::class)->issue($invoice);

        $client = Client::factory()->create();
        app(ClientCreditService::class)->add($client, '10.00');

        $this->assertSame(0, AuditLog::query()->count());
    }

    private function makeUnpaidInvoice(string $total): Invoice
    {
        return Invoice::factory()->unpaid()->create([
            'subtotal' => $total,
            'tax_amount' => '0.00',
            'total_amount' => $total,
        ]);
    }

    private function quoteInput(Client $client, string $unitPrice = '10.00'): CreateQuoteInput
    {
        return CreateQuoteInput::fromClient(
            $client,
            [
                new QuoteLineInput(
                    description: 'Quoted service',
                    unitPrice: $unitPrice,
                    billingCycle: BillingCycle::Monthly,
                ),
            ],
        );
    }
}
