<?php

namespace Tests\Feature\Orders;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CartsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_tables_exist(): void
    {
        foreach (['carts', 'cart_items'] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_carts_have_expected_columns(): void
    {
        foreach ([
            'id',
            'client_id',
            'session_id',
            'status',
            'currency',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('carts', $column),
                "Expected carts.{$column} to exist.",
            );
        }

        $this->assertFalse(
            Schema::hasColumn('carts', 'deleted_at'),
            'Expected carts to not use soft deletes.',
        );
    }

    public function test_cart_items_have_expected_columns(): void
    {
        foreach ([
            'id',
            'cart_id',
            'product_id',
            'billing_cycle',
            'custom_interval_days',
            'quantity',
            'options',
            'addons',
            'config_data',
            'unit_price',
            'setup_fee',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('cart_items', $column),
                "Expected cart_items.{$column} to exist.",
            );
        }

        $this->assertFalse(
            Schema::hasColumn('cart_items', 'deleted_at'),
            'Expected cart_items to not use soft deletes.',
        );
    }
}
