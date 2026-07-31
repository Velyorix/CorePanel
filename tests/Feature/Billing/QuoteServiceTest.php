<?php

namespace Tests\Feature\Billing;

use Core\Billing\DataTransferObjects\CreateQuoteInput;
use Core\Billing\DataTransferObjects\QuoteLineInput;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Exceptions\InvalidQuoteException;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Quote;
use Core\Billing\Models\TaxRule;
use Core\Billing\Services\PaymentService;
use Core\Billing\Services\QuoteService;
use Core\Clients\Models\Client;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuoteService $quotes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quotes = app(QuoteService::class);

        config([
            'corepanel.billing.quote_numbering.prefix' => 'QUO',
            'corepanel.billing.quote_numbering.padding' => 6,
            'corepanel.billing.quote_numbering.include_year' => true,
            'corepanel.billing.invoice_numbering.prefix' => 'INV',
            'corepanel.billing.invoice_numbering.padding' => 6,
            'corepanel.billing.invoice_numbering.include_year' => true,
            'corepanel.billing.quote_valid_days' => 30,
            'corepanel.billing.invoice_due_days' => 14,
        ]);
    }

    public function test_quote_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(QuoteService::class),
            app(QuoteService::class),
        );
    }

    public function test_quote_status_enum_covers_lifecycle(): void
    {
        $this->assertSame(
            ['draft', 'sent', 'accepted', 'declined', 'expired', 'converted', 'cancelled'],
            QuoteStatus::values(),
        );
        $this->assertTrue(QuoteStatus::Draft->isEditable());
        $this->assertTrue(QuoteStatus::Sent->isConvertible());
        $this->assertFalse(QuoteStatus::Converted->isOpen());
    }

    public function test_create_builds_draft_quote_with_taxed_totals(): void
    {
        $client = $this->makeFrClient();
        $product = Product::factory()->published()->withPricing()->create();

        $quote = $this->quotes->create(CreateQuoteInput::fromClient(
            $client,
            [
                new QuoteLineInput(
                    description: $product->name,
                    unitPrice: '100.00',
                    setupFee: '0.00',
                    productId: $product->id,
                    productName: $product->name,
                    productSlug: $product->slug,
                    billingCycle: BillingCycle::Monthly,
                ),
            ],
        ));

        $this->assertSame(QuoteStatus::Draft, $quote->status);
        $this->assertNull($quote->quote_number);
        $this->assertSame('100.00', $quote->subtotal);
        $this->assertSame('20.00', $quote->tax_amount);
        $this->assertSame('120.00', $quote->total_amount);
        $this->assertCount(1, $quote->items);
        $this->assertSame('20.00', $quote->items->first()->tax_amount);
        $this->assertTrue($client->quotes()->whereKey($quote->id)->exists());
    }

    public function test_update_replaces_draft_lines(): void
    {
        $client = $this->makeFrClient();
        $quote = $this->quotes->create($this->input($client, '10.00'));

        $updated = $this->quotes->update($quote, $this->input($client, '50.00', '5.00'));

        $this->assertSame('55.00', $updated->subtotal);
        $this->assertSame('11.00', $updated->tax_amount);
        $this->assertSame('66.00', $updated->total_amount);
        $this->assertCount(1, $updated->items);
        $this->assertSame('50.00', $updated->items->first()->unit_price);
    }

    public function test_update_rejects_non_draft(): void
    {
        $client = $this->makeFrClient();
        $quote = $this->quotes->send($this->quotes->create($this->input($client)));

        $this->expectException(InvalidQuoteException::class);

        $this->quotes->update($quote, $this->input($client, '20.00'));
    }

    public function test_send_assigns_number_and_marks_sent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 12:00:00'));

        $client = $this->makeFrClient();
        $quote = $this->quotes->send($this->quotes->create($this->input($client)));

        $this->assertSame(QuoteStatus::Sent, $quote->status);
        $this->assertSame('QUO-2026-000001', $quote->quote_number);
        $this->assertNotNull($quote->sent_at);
        $this->assertNotNull($quote->valid_until);
    }

    public function test_send_rejects_empty_items(): void
    {
        $quote = Quote::factory()->draft()->create([
            'country' => 'FR',
        ]);

        $this->expectException(InvalidQuoteException::class);

        $this->quotes->send($quote);
    }

    public function test_accept_and_decline_from_sent(): void
    {
        $client = $this->makeFrClient();
        $sent = $this->quotes->send($this->quotes->create($this->input($client)));

        $accepted = $this->quotes->accept($sent);
        $this->assertSame(QuoteStatus::Accepted, $accepted->status);
        $this->assertNotNull($accepted->accepted_at);

        $other = $this->quotes->send($this->quotes->create($this->input($client, '15.00')));
        $declined = $this->quotes->decline($other);
        $this->assertSame(QuoteStatus::Declined, $declined->status);
    }

    public function test_cancel_draft_or_sent(): void
    {
        $client = $this->makeFrClient();
        $draft = $this->quotes->create($this->input($client));
        $this->assertSame(QuoteStatus::Cancelled, $this->quotes->cancel($draft)->status);

        $sent = $this->quotes->send($this->quotes->create($this->input($client, '12.00')));
        $this->assertSame(QuoteStatus::Cancelled, $this->quotes->cancel($sent)->status);
    }

    public function test_convert_to_invoice_creates_unpaid_numbered_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 15:00:00'));

        $client = $this->makeFrClient();
        $quote = $this->quotes->send($this->quotes->create($this->input($client, '100.00')));

        $invoice = $this->quotes->convertToInvoice($quote);

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertSame('INV-2026-000001', $invoice->invoice_number);
        $this->assertNotNull($invoice->issued_at);
        $this->assertNotNull($invoice->due_at);
        $this->assertSame('100.00', $invoice->subtotal);
        $this->assertSame('20.00', $invoice->tax_amount);
        $this->assertSame('120.00', $invoice->total_amount);
        $this->assertCount(1, $invoice->items);
        $this->assertNull($invoice->order_id);

        $quote->refresh();
        $this->assertSame(QuoteStatus::Converted, $quote->status);
        $this->assertSame($invoice->id, $quote->converted_invoice_id);
        $this->assertNotNull($quote->converted_at);
        $this->assertNotNull($quote->accepted_at);
    }

    public function test_convert_to_invoice_is_idempotent(): void
    {
        $client = $this->makeFrClient();
        $quote = $this->quotes->send($this->quotes->create($this->input($client)));

        $first = $this->quotes->convertToInvoice($quote);
        $second = $this->quotes->convertToInvoice($quote->fresh() ?? $quote);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_convert_rejects_draft_and_expired(): void
    {
        $client = $this->makeFrClient();
        $draft = $this->quotes->create($this->input($client));

        try {
            $this->quotes->convertToInvoice($draft);
            $this->fail('Expected InvalidQuoteException for draft conversion.');
        } catch (InvalidQuoteException) {
            // expected
        }

        Carbon::setTestNow(Carbon::parse('2026-08-20 10:00:00'));
        $sent = $this->quotes->send($this->quotes->create(
            CreateQuoteInput::fromClient(
                $client,
                [
                    new QuoteLineInput(
                        description: 'Line',
                        unitPrice: '10.00',
                        billingCycle: BillingCycle::Monthly,
                    ),
                ],
                validUntil: Carbon::parse('2026-08-01 00:00:00'),
            ),
        ));

        $this->expectException(InvalidQuoteException::class);
        $this->quotes->convertToInvoice($sent);
    }

    public function test_convert_recalculates_tax_at_conversion_time(): void
    {
        $client = $this->makeFrClient();
        $quote = $this->quotes->send($this->quotes->create($this->input($client, '100.00')));
        $this->assertSame('20.00', $quote->tax_amount);

        TaxRule::query()->where('country', 'FR')->update(['rate' => '0.1000']);

        $invoice = $this->quotes->convertToInvoice($quote);

        $this->assertSame('10.00', $invoice->tax_amount);
        $this->assertSame('110.00', $invoice->total_amount);
    }

    public function test_converted_invoice_is_payable_via_payment_service(): void
    {
        $client = $this->makeFrClient();
        $quote = $this->quotes->send($this->quotes->create($this->input($client, '80.00')));
        $invoice = $this->quotes->convertToInvoice($quote);

        $payment = app(PaymentService::class)->initiate($invoice, ManualTransferGateway::KEY);
        $this->paymentsComplete($payment);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_create_rejects_empty_lines(): void
    {
        $client = $this->makeFrClient();

        $this->expectException(InvalidQuoteException::class);

        $this->quotes->create(CreateQuoteInput::fromClient($client, []));
    }

    private function makeFrClient(): Client
    {
        return Client::factory()->create([
            'country' => 'FR',
            'company_name' => null,
            'vat_number' => null,
        ]);
    }

    private function input(Client $client, string $unitPrice = '10.00', string $setupFee = '0.00'): CreateQuoteInput
    {
        return CreateQuoteInput::fromClient(
            $client,
            [
                new QuoteLineInput(
                    description: 'Quoted service',
                    unitPrice: $unitPrice,
                    setupFee: $setupFee,
                    billingCycle: BillingCycle::Monthly,
                ),
            ],
        );
    }

    private function paymentsComplete(\Core\Billing\Models\Payment $payment): void
    {
        app(PaymentService::class)->complete($payment, 'WIRE-TEST');
    }
}
