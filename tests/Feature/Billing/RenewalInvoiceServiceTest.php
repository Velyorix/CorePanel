<?php

namespace Tests\Feature\Billing;

use Carbon\Carbon;
use Core\Billing\Contracts\RenewableBillableSource;
use Core\Billing\DataTransferObjects\RenewalInvoiceInput;
use Core\Billing\DataTransferObjects\RenewalLineInput;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\TaxRule;
use Core\Billing\Services\InvoiceGenerationService;
use Core\Billing\Services\NullRenewableBillableSource;
use Core\Billing\Services\RenewalInvoiceService;
use Core\Clients\Models\Client;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class RenewalInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceGenerationService $invoiceGeneration;

    private RenewalInvoiceService $renewals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceGeneration = app(InvoiceGenerationService::class);
        $this->renewals = app(RenewalInvoiceService::class);
    }

    public function test_renewal_invoice_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(RenewalInvoiceService::class),
            app(RenewalInvoiceService::class),
        );
        $this->assertInstanceOf(
            NullRenewableBillableSource::class,
            app(RenewableBillableSource::class),
        );
    }

    public function test_create_renewal_builds_draft_invoice_without_order_or_number(): void
    {
        $input = $this->makeRenewalInput();

        $invoice = $this->invoiceGeneration->createRenewal($input);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->invoice_number);
        $this->assertNull($invoice->order_id);
        $this->assertSame($input->clientId, $invoice->client_id);
        $this->assertCount(1, $invoice->items);
    }

    public function test_create_renewal_sets_service_id_and_zero_setup_fee(): void
    {
        $product = Product::factory()->published()->withPricing()->create();
        $input = $this->makeRenewalInput(line: new RenewalLineInput(
            description: 'VPS Renewal',
            unitPrice: '19.99',
            quantity: 1,
            productId: $product->id,
            productName: $product->name,
            productSlug: $product->slug,
            billingCycle: BillingCycle::Monthly,
        ));

        $invoice = $this->invoiceGeneration->createRenewal($input);
        $item = $invoice->items->first();

        $this->assertSame(9001, $item->service_id);
        $this->assertSame('0.00', $item->setup_fee);
        $this->assertSame('19.99', $item->unit_price);
        $this->assertSame('19.99', $item->line_total);
        $this->assertSame(BillingCycle::Monthly, $item->billing_cycle);
        $this->assertSame($product->id, $item->product_id);
    }

    public function test_create_renewal_applies_tax_via_tax_calculation_service(): void
    {
        $client = Client::factory()->create([
            'country' => 'FR',
            'company_name' => null,
            'vat_number' => null,
        ]);

        $input = RenewalInvoiceInput::fromClient(
            $client,
            serviceId: 42,
            billingPeriodEnd: now()->addDays(7),
            line: new RenewalLineInput(
                description: 'Cloud VPS',
                unitPrice: '100.00',
                billingCycle: BillingCycle::Monthly,
            ),
        );

        $invoice = $this->invoiceGeneration->createRenewal($input);

        $this->assertSame('100.00', $invoice->subtotal);
        $this->assertSame('20.00', $invoice->tax_amount);
        $this->assertSame('120.00', $invoice->total_amount);
        $this->assertSame('20.00', $invoice->items->first()->tax_amount);
    }

    public function test_create_renewal_sets_due_at_to_billing_period_end(): void
    {
        $due = Carbon::parse('2026-08-01 12:00:00');
        $input = $this->makeRenewalInput(billingPeriodEnd: $due);

        $invoice = $this->invoiceGeneration->createRenewal($input);

        $this->assertTrue($due->equalTo($invoice->due_at));
    }

    public function test_create_renewal_is_idempotent_for_same_service(): void
    {
        $input = $this->makeRenewalInput(serviceId: 55);

        $first = $this->invoiceGeneration->createRenewal($input);
        $second = $this->invoiceGeneration->createRenewal($input);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_create_renewal_allows_new_invoice_after_previous_paid(): void
    {
        $input = $this->makeRenewalInput(serviceId: 77);
        $first = $this->invoiceGeneration->createRenewal($input);

        $first->forceFill([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ])->save();

        $second = $this->invoiceGeneration->createRenewal($input);

        $this->assertFalse($first->is($second));
        $this->assertSame(2, Invoice::query()->count());
        $this->assertSame(InvoiceStatus::Draft, $second->status);
    }

    public function test_generate_due_with_null_source_creates_nothing(): void
    {
        $result = $this->renewals->generateDue();

        $this->assertSame(0, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(0, $result->errors);
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_generate_due_creates_from_source_and_skips_open_renewals(): void
    {
        $first = $this->makeRenewalInput(serviceId: 101, unitPrice: '10.00');
        $second = $this->makeRenewalInput(serviceId: 102, unitPrice: '20.00');

        $this->invoiceGeneration->createRenewal($first);

        $this->app->forgetInstance(RenewalInvoiceService::class);
        $this->app->instance(RenewableBillableSource::class, new class($first, $second) implements RenewableBillableSource
        {
            public function __construct(
                private RenewalInvoiceInput $first,
                private RenewalInvoiceInput $second,
            ) {
            }

            public function dueForRenewal(\Carbon\CarbonInterface $asOf, int $daysBefore): Collection
            {
                return collect([$this->first, $this->second]);
            }
        });

        $result = app(RenewalInvoiceService::class)->generateDue();

        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, $result->errors);
        $this->assertSame(2, Invoice::query()->count());
    }

    public function test_generate_due_respects_disabled_config(): void
    {
        config(['corepanel.billing.renewal.enabled' => false]);

        $this->app->forgetInstance(RenewalInvoiceService::class);
        $this->app->instance(RenewableBillableSource::class, new class implements RenewableBillableSource
        {
            public function dueForRenewal(\Carbon\CarbonInterface $asOf, int $daysBefore): Collection
            {
                throw new \RuntimeException('Source should not be called when renewals are disabled.');
            }
        });

        $result = app(RenewalInvoiceService::class)->generateDue();

        $this->assertSame(0, $result->created);
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_create_renewal_rejects_invalid_service_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->invoiceGeneration->createRenewal(
            $this->makeRenewalInput(serviceId: 0),
        );
    }

    public function test_eu_cross_border_b2b_renewal_is_reverse_charge(): void
    {
        TaxRule::factory()->deStandard()->create();

        $client = Client::factory()->create([
            'country' => 'DE',
            'company_name' => 'German GmbH',
            'vat_number' => 'DE123456789',
        ]);

        $input = RenewalInvoiceInput::fromClient(
            $client,
            serviceId: 88,
            billingPeriodEnd: now()->addWeek(),
            line: new RenewalLineInput(
                description: 'Dedicated Server',
                unitPrice: '50.00',
                billingCycle: BillingCycle::Monthly,
            ),
        );

        $invoice = $this->invoiceGeneration->createRenewal($input);

        $this->assertSame('50.00', $invoice->subtotal);
        $this->assertSame('0.00', $invoice->tax_amount);
        $this->assertSame('50.00', $invoice->total_amount);
    }

    private function makeRenewalInput(
        int $serviceId = 9001,
        ?Carbon $billingPeriodEnd = null,
        ?RenewalLineInput $line = null,
        string $unitPrice = '10.00',
    ): RenewalInvoiceInput {
        $client = Client::factory()->create([
            'country' => 'FR',
            'company_name' => null,
            'vat_number' => null,
        ]);

        return RenewalInvoiceInput::fromClient(
            $client,
            serviceId: $serviceId,
            billingPeriodEnd: $billingPeriodEnd ?? now()->addDays(7),
            line: $line ?? new RenewalLineInput(
                description: 'Renewal line',
                unitPrice: $unitPrice,
                billingCycle: BillingCycle::Monthly,
            ),
        );
    }
}
