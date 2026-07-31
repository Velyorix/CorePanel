<?php

namespace Tests\Feature\Orders;

use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrdersSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_tables_exist(): void
    {
        foreach (['orders', 'order_items'] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_orders_have_expected_columns(): void
    {
        foreach ([
            'id',
            'order_number',
            'client_id',
            'created_by',
            'source',
            'cart_id',
            'status',
            'currency',
            'payment_method',
            'coupon_code',
            'coupon_id',
            'discount_amount',
            'contact_name',
            'contact_email',
            'company_name',
            'vat_number',
            'address',
            'city',
            'country',
            'postal_code',
            'phone',
            'notes',
            'subtotal_recurring',
            'subtotal_setup',
            'tax_amount',
            'total_amount',
            'placed_at',
            'paid_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('orders', $column),
                "Expected orders.{$column} to exist.",
            );
        }
    }

    public function test_order_items_have_expected_columns(): void
    {
        foreach ([
            'id',
            'order_id',
            'product_id',
            'product_name',
            'product_slug',
            'billing_cycle',
            'custom_interval_days',
            'quantity',
            'options',
            'addons',
            'config_data',
            'unit_price',
            'setup_fee',
            'line_total',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('order_items', $column),
                "Expected order_items.{$column} to exist.",
            );
        }
    }

    public function test_order_status_and_source_enums_cover_lifecycle(): void
    {
        $this->assertSame(
            ['draft', 'pending_payment', 'paid', 'cancelled'],
            array_map(
                static fn (OrderStatus $status): string => $status->value,
                OrderStatus::cases(),
            ),
        );

        $this->assertSame(
            ['client_checkout', 'admin'],
            array_map(
                static fn (OrderSource $source): string => $source->value,
                OrderSource::cases(),
            ),
        );

        $this->assertTrue(OrderStatus::PendingPayment->isOpen());
        $this->assertFalse(OrderStatus::Paid->isOpen());
        $this->assertTrue(OrderStatus::Paid->isPlaced());
    }

    public function test_order_factory_persists_enhanced_columns(): void
    {
        $order = Order::factory()->pendingPayment()->create([
            'notes' => 'Schema smoke note',
        ]);

        $this->assertNotNull($order->order_number);
        $this->assertSame(OrderSource::ClientCheckout, $order->source);
        $this->assertSame('Schema smoke note', $order->notes);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->cancelled_at);
    }
}
