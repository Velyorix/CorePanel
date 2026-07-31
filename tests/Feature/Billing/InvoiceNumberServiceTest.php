<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\BillingSequence;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\BillingSettings;
use Core\Billing\Services\InvoiceGenerationService;
use Core\Billing\Services\InvoiceNumberService;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class InvoiceNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceNumberService $numberService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->numberService = app(InvoiceNumberService::class);

        config([
            'corepanel.billing.invoice_numbering.prefix' => 'INV',
            'corepanel.billing.invoice_numbering.padding' => 6,
            'corepanel.billing.invoice_numbering.include_year' => true,
            'corepanel.billing.invoice_numbering.reset_yearly' => true,
            'corepanel.billing.invoice_numbering.separator' => '-',
        ]);
    }

    public function test_invoice_number_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(InvoiceNumberService::class),
            app(InvoiceNumberService::class),
        );
    }

    public function test_assign_number_sets_formatted_number_on_draft_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        $invoice = Invoice::factory()->draft()->create();

        $numbered = $this->numberService->assignNumber($invoice);

        $this->assertSame('INV-2026-000001', $numbered->invoice_number);
        $this->assertSame(InvoiceStatus::Draft, $numbered->status);
        $this->assertSame(1, BillingSequence::query()->where('name', 'invoice')->value('current_value'));
    }

    public function test_assign_number_is_idempotent_when_already_numbered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        $invoice = Invoice::factory()->draft()->create();
        $first = $this->numberService->assignNumber($invoice);
        $second = $this->numberService->assignNumber($first);

        $this->assertSame($first->invoice_number, $second->invoice_number);
        $this->assertSame(1, BillingSequence::query()->where('name', 'invoice')->value('current_value'));
    }

    public function test_assign_number_increments_sequence_on_second_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        $first = $this->numberService->assignNumber(Invoice::factory()->draft()->create());
        $second = $this->numberService->assignNumber(Invoice::factory()->draft()->create());

        $this->assertSame('INV-2026-000001', $first->invoice_number);
        $this->assertSame('INV-2026-000002', $second->invoice_number);
        $this->assertSame(2, BillingSequence::query()->where('name', 'invoice')->value('current_value'));
    }

    public function test_assign_number_rejects_non_draft_invoice(): void
    {
        $invoice = Invoice::factory()->unpaid()->create([
            'invoice_number' => null,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only draft invoices can receive an invoice number.');

        $this->numberService->assignNumber($invoice);
    }

    public function test_prefix_comes_from_billing_settings_with_config_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        app(BillingSettings::class)->setInvoicePrefix('FC');

        $invoice = $this->numberService->assignNumber(Invoice::factory()->draft()->create());

        $this->assertSame('FC-2026-000001', $invoice->invoice_number);
        $this->assertSame('FC', app(BillingSettings::class)->invoicePrefix());
    }

    public function test_yearly_reset_starts_sequence_at_one_in_new_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-31 23:00:00'));

        $this->numberService->assignNumber(Invoice::factory()->draft()->create());
        $this->assertSame(1, BillingSequence::query()->where('name', 'invoice')->value('current_value'));

        Carbon::setTestNow(Carbon::parse('2027-01-01 00:05:00'));

        $nextYear = $this->numberService->assignNumber(Invoice::factory()->draft()->create());

        $this->assertSame('INV-2027-000001', $nextYear->invoice_number);
        $this->assertSame(1, BillingSequence::query()->where('name', 'invoice')->value('current_value'));
        $this->assertSame(2027, BillingSequence::query()->where('name', 'invoice')->value('year'));
    }

    public function test_sequential_assignments_produce_unique_numbers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        $numbers = [];

        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->numberService
                ->assignNumber(Invoice::factory()->draft()->create())
                ->invoice_number;
        }

        $this->assertSame([
            'INV-2026-000001',
            'INV-2026-000002',
            'INV-2026-000003',
            'INV-2026-000004',
            'INV-2026-000005',
        ], $numbers);
        $this->assertCount(5, array_unique($numbers));
    }

    public function test_preview_next_does_not_consume_sequence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        $this->assertSame('INV-2026-000001', $this->numberService->previewNext());
        $this->assertSame(0, BillingSequence::query()->where('name', 'invoice')->value('current_value'));

        $this->numberService->assignNumber(Invoice::factory()->draft()->create());

        $this->assertSame('INV-2026-000002', $this->numberService->previewNext());
        $this->assertSame(1, BillingSequence::query()->where('name', 'invoice')->value('current_value'));
    }

    public function test_create_from_order_still_leaves_number_null_until_assign(): void
    {
        $order = Order::factory()->paid()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);

        $invoice = app(InvoiceGenerationService::class)->createFromOrder($order);

        $this->assertNull($invoice->invoice_number);

        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        $numbered = $this->numberService->assignNumber($invoice);

        $this->assertSame('INV-2026-000001', $numbered->invoice_number);
        $this->assertSame(InvoiceStatus::Draft, $numbered->status);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
