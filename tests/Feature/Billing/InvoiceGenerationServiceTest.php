<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\InvoiceGenerationService;
use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Core\Orders\Services\OrderService;
use Core\Products\Enums\BillingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class InvoiceGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceGenerationService $invoiceGeneration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceGeneration = app(InvoiceGenerationService::class);

        config([
            'corepanel.billing.tax_preview_rate' => 0.20,
        ]);
    }

    public function test_invoice_generation_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(InvoiceGenerationService::class),
            app(InvoiceGenerationService::class),
        );
    }

    public function test_invoice_status_enum_covers_cdc_lifecycle(): void
    {
        $this->assertSame(
            ['draft', 'unpaid', 'paid', 'overdue', 'cancelled', 'refunded'],
            InvoiceStatus::values(),
        );

        $this->assertTrue(InvoiceStatus::Draft->isOpen());
        $this->assertFalse(InvoiceStatus::Paid->isOpen());
    }

    public function test_create_from_order_builds_draft_invoice_with_items(): void
    {
        $order = $this->makePaidOrder();

        $invoice = $this->invoiceGeneration->createFromOrder($order);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->invoice_number);
        $this->assertSame($order->id, $invoice->order_id);
        $this->assertSame($order->client_id, $invoice->client_id);
        $this->assertNull($invoice->issued_at);
        $this->assertNull($invoice->due_at);
        $this->assertNull($invoice->paid_at);
        $this->assertCount(1, $invoice->items);
        $this->assertTrue($order->fresh()->invoice->is($invoice));
    }

    public function test_create_from_order_copies_billing_snapshot_and_totals(): void
    {
        $order = $this->makePaidOrder([
            'contact_name' => 'Alice Billing',
            'contact_email' => 'alice@billing.test',
            'company_name' => 'Billing Co',
            'vat_number' => 'FR123',
            'address' => '12 Rue Facture',
            'city' => 'Paris',
            'country' => 'FR',
            'postal_code' => '75002',
            'phone' => '+33111111111',
            'notes' => 'Invoice notes',
            'currency' => 'EUR',
            'subtotal_recurring' => '19.99',
            'subtotal_setup' => '5.00',
            'tax_amount' => '5.00',
            'total_amount' => '29.99',
        ], [
            'product_name' => 'Cloud VPS',
            'product_slug' => 'cloud-vps',
            'unit_price' => '19.99',
            'setup_fee' => '5.00',
            'line_total' => '24.99',
            'quantity' => 1,
        ]);

        $invoice = $this->invoiceGeneration->createFromOrder($order);

        $this->assertSame('Alice Billing', $invoice->contact_name);
        $this->assertSame('alice@billing.test', $invoice->contact_email);
        $this->assertSame('Billing Co', $invoice->company_name);
        $this->assertSame('FR123', $invoice->vat_number);
        $this->assertSame('12 Rue Facture', $invoice->address);
        $this->assertSame('Paris', $invoice->city);
        $this->assertSame('FR', $invoice->country);
        $this->assertSame('75002', $invoice->postal_code);
        $this->assertSame('+33111111111', $invoice->phone);
        $this->assertSame('Invoice notes', $invoice->notes);
        $this->assertSame('EUR', $invoice->currency);
        $this->assertSame('24.99', $invoice->subtotal);
        $this->assertSame('5.00', $invoice->tax_amount);
        $this->assertSame('29.99', $invoice->total_amount);
    }

    public function test_create_from_order_maps_order_items_to_invoice_items(): void
    {
        $order = $this->makePaidOrder([], [
            'product_name' => 'VPS Starter',
            'product_slug' => 'vps-starter',
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 2,
            'options' => ['hostname' => 'node-01.example.test'],
            'addons' => ['backup'],
            'unit_price' => '10.00',
            'setup_fee' => '2.00',
            'line_total' => '22.00',
        ]);

        $invoice = $this->invoiceGeneration->createFromOrder($order);
        $item = $invoice->items->first();

        $this->assertSame('VPS Starter', $item->description);
        $this->assertSame('VPS Starter', $item->product_name);
        $this->assertSame('vps-starter', $item->product_slug);
        $this->assertSame(BillingCycle::Monthly, $item->billing_cycle);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(['hostname' => 'node-01.example.test'], $item->options);
        $this->assertSame(['backup'], $item->addons);
        $this->assertSame('10.00', $item->unit_price);
        $this->assertSame('2.00', $item->setup_fee);
        $this->assertSame('0.00', $item->tax_amount);
        $this->assertSame('22.00', $item->line_total);
        $this->assertNull($item->service_id);
    }

    public function test_create_from_order_is_idempotent_for_same_order(): void
    {
        $order = $this->makePaidOrder();

        $first = $this->invoiceGeneration->createFromOrder($order);
        $second = $this->invoiceGeneration->createFromOrder($order);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(1, $first->items()->count());
    }

    public function test_create_from_order_rejects_non_paid_order(): void
    {
        $order = Order::factory()->pendingPayment()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only paid orders can be converted into invoices.');

        $this->invoiceGeneration->createFromOrder($order);
    }

    public function test_create_from_order_rejects_order_without_items(): void
    {
        $order = Order::factory()->paid()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot generate an invoice from an order without line items.');

        $this->invoiceGeneration->createFromOrder($order);
    }

    public function test_paid_order_via_order_service_then_invoice_generation(): void
    {
        $order = Order::factory()->pendingPayment()->create([
            'subtotal_recurring' => '10.00',
            'subtotal_setup' => '0.00',
            'tax_amount' => '2.00',
            'total_amount' => '12.00',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Integration Product',
            'unit_price' => '10.00',
            'setup_fee' => '0.00',
            'line_total' => '10.00',
        ]);

        $paid = app(OrderService::class)->markPaid($order);
        $this->assertSame(OrderStatus::Paid, $paid->status);

        $invoice = $this->invoiceGeneration->createFromOrder($paid);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame($paid->id, $invoice->order_id);
        $this->assertSame('10.00', $invoice->subtotal);
        $this->assertSame('12.00', $invoice->total_amount);
        $this->assertSame('Integration Product', $invoice->items->first()->description);
    }

    public function test_invoice_factory_persists_draft_without_number(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'subtotal' => '15.00',
            'total_amount' => '18.00',
        ]);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->invoice_number);
        $this->assertSame('15.00', $invoice->subtotal);
    }

    /**
     * @param  array<string, mixed>  $orderAttributes
     * @param  array<string, mixed>  $itemAttributes
     */
    private function makePaidOrder(array $orderAttributes = [], array $itemAttributes = []): Order
    {
        $order = Order::factory()->paid()->create([
            'client_id' => Client::factory(),
            'subtotal_recurring' => '10.00',
            'subtotal_setup' => '0.00',
            'tax_amount' => '2.00',
            'total_amount' => '12.00',
            ...$orderAttributes,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Default Product',
            'unit_price' => '10.00',
            'setup_fee' => '0.00',
            'line_total' => '10.00',
            ...$itemAttributes,
        ]);

        return $order->fresh(['items', 'client']) ?? $order;
    }
}
