<?php

namespace Tests\Feature\Servers;

use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Models\NodeGroupRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServersRegistrySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_servers_registry_tables_exist(): void
    {
        foreach (['node_groups', 'nodes', 'node_group_relations', 'node_logs'] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_nodes_table_has_expected_columns(): void
    {
        foreach ([
            'id',
            'name',
            'type',
            'module',
            'hostname',
            'ip_address',
            'api_url',
            'status',
            'max_services',
            'max_cpu_cores',
            'max_ram_mb',
            'max_disk_gb',
            'max_bandwidth_mbps',
            'sort_order',
            'node_group_id',
            'credentials',
            'config',
            'created_at',
            'updated_at',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('nodes', $column),
                "Expected nodes.{$column} to exist.",
            );
        }
    }

    public function test_node_group_relations_table_has_expected_columns(): void
    {
        foreach ([
            'id',
            'node_id',
            'node_group_id',
            'is_primary',
            'sort_order',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('node_group_relations', $column),
                "Expected node_group_relations.{$column} to exist.",
            );
        }
    }

    public function test_node_logs_table_has_expected_columns(): void
    {
        foreach ([
            'id',
            'node_id',
            'action',
            'status',
            'response',
            'performed_by',
            'created_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('node_logs', $column),
                "Expected node_logs.{$column} to exist.",
            );
        }
    }

    public function test_node_can_be_assigned_to_multiple_groups_via_relations(): void
    {
        $node = Node::factory()->create(['node_group_id' => null]);
        $primary = NodeGroup::factory()->create(['key' => 'eu-west']);
        $secondary = NodeGroup::factory()->create(['key' => 'game-pool']);

        NodeGroupRelation::query()->create([
            'node_id' => $node->id,
            'node_group_id' => $primary->id,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        NodeGroupRelation::query()->create([
            'node_id' => $node->id,
            'node_group_id' => $secondary->id,
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $node->refresh()->load('groups');

        $this->assertCount(2, $node->groups);
        $this->assertTrue((bool) $node->groups->firstWhere('id', $primary->id)?->pivot->is_primary);
        $this->assertFalse((bool) $node->groups->firstWhere('id', $secondary->id)?->pivot->is_primary);
        $this->assertCount(1, $primary->fresh()->assignedNodes);
    }
}
