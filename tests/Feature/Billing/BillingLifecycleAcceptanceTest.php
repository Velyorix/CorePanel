<?php

namespace Tests\Feature\Billing;

use App\Jobs\GenerateRenewalInvoices;
use App\Jobs\ProcessOverdueSuspensions;
use Carbon\Carbon;
use Core\Billing\Contracts\OverdueServiceActions;
use Core\Billing\Contracts\RenewableBillableSource;
use Core\Billing\DataTransferObjects\CreateQuoteInput;
use Core\Billing\DataTransferObjects\QuoteLineInput;
use Core\Billing\DataTransferObjects\RenewalInvoiceInput;
use Core\Billing\DataTransferObjects\RenewalLineInput;
use Core\Billing\Enums\CreditNoteStatus;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\OverdueInvoiceAction;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\ClientCreditService;
use Core\Billing\Services\CreditNoteService;
use Core\Billing\Services\InvoiceGenerationService;
use Core\Billing\Services\InvoiceReminderService;
use Core\Billing\Services\InvoiceService;
use Core\Billing\Services\PaymentService;
use Core\Billing\Services\QuoteService;
use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Core\Orders\Services\OrderService;
use Core\Products\Enums\BillingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * End-to-end billing lifecycle smoke.
 * Narrower billing Feature tests cover individual slices.
 */
class BillingLifecycleAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{action: string, service_id: int, invoice_id: int}> */
    private array $lifecycleActions = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.billing.tax_preview_rate' => 0.20,
            'corepanel.billing.renewal.enabled' => true,
            'corepanel.billing.reminders.enabled' => true,
            'corepanel.billing.suspension.enabled' => true,
            'corepanel.billing.suspension.suspend_after_days' => 3,
            'corepanel.billing.suspension.terminate_after_days' => 7,
            'corepanel.billing.suspension.skip_if_credit_available' => false,
            'corepanel.billing.manual_transfer.enabled' => true,
        ]);
    }

    public function test_paid_order_generates_issued_invoice_and_payment_marks_paid(): void
    {
        $order = Order::factory()->pendingPayment()->create([
            'client_id' => Client::factory()->create([
                'country' => 'FR',
                'company_name' => null,
                'vat_number' => null,
            ]),
            'contact_name' => 'Order Client',
            'contact_email' => 'order@billing.test',
            'country' => 'FR',
            'subtotal_recurring' => '100.00',
            'subtotal_setup' => '0.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Acceptance VPS',
            'unit_price' => '100.00',
            'setup_fee' => '0.00',
            'line_total' => '100.00',
            'billing_cycle' => BillingCycle::Monthly,
        ]);

        $paid = app(OrderService::class)->markPaid($order);
        $this->assertSame(OrderStatus::Paid, $paid->status);

        $draft = app(InvoiceGenerationService::class)->createFromOrder($paid);
        $this->assertSame(InvoiceStatus::Draft, $draft->status);
        $this->assertSame($paid->id, $draft->order_id);

        $invoice = app(InvoiceService::class)->issue($draft);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertNotNull($invoice->issued_at);
        $this->assertNotNull($invoice->due_at);

        $payment = app(PaymentService::class)->initiate($invoice, ManualTransferGateway::KEY);
        $this->assertSame(PaymentStatus::Pending, $payment->status);

        app(PaymentService::class)->complete($payment, 'WIRE-ACCEPT-1');

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_quote_converts_to_invoice_then_payment_and_wallet_credit_note(): void
    {
        $client = Client::factory()->create([
            'country' => 'FR',
            'company_name' => null,
            'vat_number' => null,
            'credit_balance' => '0.00',
        ]);

        $quotes = app(QuoteService::class);
        $quote = $quotes->send($quotes->create(CreateQuoteInput::fromClient(
            $client,
            [
                new QuoteLineInput(
                    description: 'Quoted cloud box',
                    unitPrice: '80.00',
                    billingCycle: BillingCycle::Monthly,
                ),
            ],
        )));

        $this->assertSame(QuoteStatus::Sent, $quote->status);

        $invoice = $quotes->convertToInvoice($quote);
        $this->assertSame(QuoteStatus::Converted, $quote->fresh()->status);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertSame($client->id, $invoice->client_id);

        $payment = app(PaymentService::class)->initiate($invoice, ManualTransferGateway::KEY);
        app(PaymentService::class)->complete($payment, 'WIRE-QUOTE-1');
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $creditNotes = app(CreditNoteService::class);
        $note = $creditNotes->createFromInvoice($invoice->fresh(), '80.00', reason: 'Partial goodwill');
        $issued = $creditNotes->issueToWallet($note);

        $this->assertSame(CreditNoteStatus::Issued, $issued->status);
        $this->assertSame('80.00', app(ClientCreditService::class)->balance($client));
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_renewal_job_then_overdue_pipeline_suspends_and_terminates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 10:00:00'));

        $client = Client::factory()->create([
            'country' => 'FR',
            'company_name' => null,
            'vat_number' => null,
            'credit_balance' => '0.00',
        ]);

        $renewalInput = RenewalInvoiceInput::fromClient(
            $client,
            serviceId: 4242,
            billingPeriodEnd: Carbon::parse('2026-07-05'),
            line: new RenewalLineInput(
                description: 'Monthly renewal',
                unitPrice: '50.00',
                billingCycle: BillingCycle::Monthly,
            ),
        );

        $this->app->instance(RenewableBillableSource::class, new class($renewalInput) implements RenewableBillableSource
        {
            public function __construct(private readonly RenewalInvoiceInput $input)
            {
            }

            public function dueForRenewal(\Carbon\CarbonInterface $asOf, int $daysBefore): Collection
            {
                return collect([$this->input]);
            }
        });

        app(GenerateRenewalInvoices::class)->handle(app(\Core\Billing\Services\RenewalInvoiceService::class));

        $draft = Invoice::query()
            ->where('client_id', $client->id)
            ->whereNull('order_id')
            ->firstOrFail();

        $this->assertSame(InvoiceStatus::Draft, $draft->status);
        $this->assertSame(4242, $draft->items->first()->service_id);

        $invoice = app(InvoiceService::class)->issue($draft);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);

        // Force a known due date for the automation window.
        $invoice->forceFill([
            'due_at' => Carbon::parse('2026-07-10')->startOfDay(),
        ])->save();

        $this->lifecycleActions = [];
        $this->bindLifecycleActions();

        Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00')); // T+3

        app(InvoiceReminderService::class)->process(Carbon::parse('2026-07-13'));
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);

        app(ProcessOverdueSuspensions::class)->handle(app(\Core\Billing\Services\OverdueSuspensionService::class));

        $this->assertSame(OverdueInvoiceAction::Suspended, $invoice->fresh()->overdue_action);
        $this->assertSame([
            ['action' => 'suspend', 'service_id' => 4242, 'invoice_id' => $invoice->id],
        ], $this->lifecycleActions);

        array_splice($this->lifecycleActions, 0);
        Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00')); // T+7

        app(ProcessOverdueSuspensions::class)->handle(app(\Core\Billing\Services\OverdueSuspensionService::class));

        $this->assertSame(OverdueInvoiceAction::Terminated, $invoice->fresh()->overdue_action);
        $this->assertSame([
            ['action' => 'terminate', 'service_id' => 4242, 'invoice_id' => $invoice->id],
        ], $this->lifecycleActions);

        Carbon::setTestNow();
    }

    private function bindLifecycleActions(): void
    {
        $fake = new class($this->lifecycleActions) implements OverdueServiceActions
        {
            /** @param list<array{action: string, service_id: int, invoice_id: int}> $log */
            public function __construct(private array &$log)
            {
            }

            public function suspend(Invoice $invoice, int $serviceId, string $reason): void
            {
                $this->log[] = [
                    'action' => 'suspend',
                    'service_id' => $serviceId,
                    'invoice_id' => $invoice->id,
                ];
            }

            public function terminate(Invoice $invoice, int $serviceId, string $reason): void
            {
                $this->log[] = [
                    'action' => 'terminate',
                    'service_id' => $serviceId,
                    'invoice_id' => $invoice->id,
                ];
            }
        };

        $this->app->instance(OverdueServiceActions::class, $fake);
    }
}
