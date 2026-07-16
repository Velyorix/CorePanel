<?php

namespace Tests\Feature\Products;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductsCatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_catalog_tables_exist(): void
    {
        foreach (['product_categories', 'products', 'product_pricing'] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_product_categories_have_expected_columns(): void
    {
        foreach ([
            'id',
            'parent_id',
            'name',
            'slug',
            'description',
            'sort_order',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('product_categories', $column),
                "Expected product_categories.{$column} to exist.",
            );
        }
    }

    public function test_products_have_expected_columns(): void
    {
        foreach ([
            'id',
            'category_id',
            'name',
            'slug',
            'description',
            'type',
            'module',
            'status',
            'sort_order',
            'created_at',
            'updated_at',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('products', $column),
                "Expected products.{$column} to exist.",
            );
        }
    }

    public function test_product_pricing_have_expected_columns(): void
    {
        foreach ([
            'id',
            'product_id',
            'billing_cycle',
            'price',
            'setup_fee',
            'is_enabled',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('product_pricing', $column),
                "Expected product_pricing.{$column} to exist.",
            );
        }
    }
}
