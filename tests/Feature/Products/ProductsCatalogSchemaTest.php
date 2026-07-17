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
        foreach (['product_categories', 'products', 'product_pricing', 'product_options', 'product_addons'] as $table) {
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
            'module_capabilities',
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

    public function test_product_options_table_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('product_options'));

        foreach ([
            'id',
            'product_id',
            'key',
            'name',
            'type',
            'required',
            'sort_order',
            'config',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('product_options', $column),
                "Expected product_options.{$column} to exist.",
            );
        }
    }

    public function test_product_addons_table_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('product_addons'));

        foreach ([
            'id',
            'product_id',
            'key',
            'name',
            'description',
            'price',
            'setup_fee',
            'billing_cycle',
            'is_enabled',
            'sort_order',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('product_addons', $column),
                "Expected product_addons.{$column} to exist.",
            );
        }
    }
}
