<?php

namespace Tests\Feature\Orders;

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
            'client_id',
            'cart_id',
            'status',
            'currency',
            'payment_method',
            'coupon_code',
            'contact_name',
            'contact_email',
            'company_name',
            'vat_number',
            'address',
            'city',
            'country',
            'postal_code',
            'phone',
            'subtotal_recurring',
            'subtotal_setup',
            'tax_amount',
            'total_amount',
            'placed_at',
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
}
