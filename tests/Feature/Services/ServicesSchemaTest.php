<?php

namespace Tests\Feature\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema smoke for services migrations.
 */
class ServicesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_tables_exist(): void
    {
        foreach ([
            'services',
            'service_actions_log',
            'service_config',
        ] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_services_have_expected_columns(): void
    {
        foreach ([
            'id',
            'client_id',
            'product_id',
            'order_id',
            'order_item_id',
            'status',
            'module',
            'external_id',
            'billing_cycle',
            'custom_interval_days',
            'config_data',
            'ip_address',
            'hostname',
            'node_id',
            'started_at',
            'ended_at',
            'renewal_date',
            'next_billing_date',
            'provisioned_at',
            'suspended_at',
            'terminated_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('services', $column),
                "Expected services.{$column} to exist.",
            );
        }
    }

    public function test_service_actions_log_have_expected_columns(): void
    {
        foreach ([
            'id',
            'service_id',
            'action',
            'status',
            'response',
            'performed_by',
            'created_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('service_actions_log', $column),
                "Expected service_actions_log.{$column} to exist.",
            );
        }

        $this->assertFalse(
            Schema::hasColumn('service_actions_log', 'updated_at'),
            'service_actions_log should be append-only without updated_at.',
        );
    }

    public function test_service_config_have_expected_columns(): void
    {
        foreach ([
            'id',
            'service_id',
            'data',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('service_config', $column),
                "Expected service_config.{$column} to exist.",
            );
        }
    }
}
