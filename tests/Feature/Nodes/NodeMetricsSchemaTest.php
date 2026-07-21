<?php

namespace Tests\Feature\Nodes;

use Core\Nodes\Models\Node;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NodeMetricsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_node_metrics_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('node_metrics'));
    }

    public function test_node_metrics_table_has_expected_columns(): void
    {
        foreach ([
            'id',
            'node_id',
            'current_services',
            'max_services',
            'cpu_usage',
            'ram_usage',
            'disk_usage',
            'network_in',
            'network_out',
            'load_average',
            'capacity_available',
            'status',
            'payload',
            'collected_at',
            'created_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('node_metrics', $column),
                "Expected node_metrics.{$column} to exist.",
            );
        }
    }

    public function test_node_has_metrics_relation(): void
    {
        $node = Node::factory()->forModule('stub')->create();

        $this->assertTrue(method_exists($node, 'metrics'));
    }
}
