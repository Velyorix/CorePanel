<?php

namespace Tests\Feature\Nodes;

use App\Models\User;
use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Enums\NodeMetricStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeHealthCheck;
use Core\Nodes\Models\NodeMetric;
use Core\Nodes\Services\NodeMonitoringService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private NodeMonitoringService $monitoring;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.nodes.monitoring.history_hours' => 24,
            'corepanel.nodes.monitoring.bucket_minutes' => 60,
        ]);

        $this->monitoring = app(NodeMonitoringService::class);
    }

    public function test_monitoring_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeMonitoringService::class),
            app(NodeMonitoringService::class),
        );
    }

    public function test_builds_node_monitoring_view_with_metric_charts(): void
    {
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'monitoring-node.example.test',
        ]);

        NodeMetric::factory()->forNode($node)->create([
            'cpu_usage' => 12.5,
            'ram_usage' => 4096,
            'disk_usage' => 120,
            'load_average' => 1.5,
            'network_in' => 10,
            'network_out' => 5,
            'status' => NodeMetricStatus::Success,
            'collected_at' => now()->subHour(),
        ]);

        NodeMetric::factory()->forNode($node)->create([
            'cpu_usage' => 25.0,
            'ram_usage' => 8192,
            'disk_usage' => 140,
            'load_average' => 2.0,
            'network_in' => 20,
            'network_out' => 12,
            'status' => NodeMetricStatus::Success,
            'collected_at' => now()->subMinutes(30),
        ]);

        NodeHealthCheck::query()->create([
            'node_id' => $node->id,
            'state' => NodeHealthState::Online,
            'checked_at' => now()->subHour(),
            'created_at' => now(),
        ]);

        NodeHealthCheck::query()->create([
            'node_id' => $node->id,
            'state' => NodeHealthState::Online,
            'checked_at' => now()->subMinutes(15),
            'created_at' => now(),
        ]);

        $view = $this->monitoring->forNode($node);

        $this->assertSame($node->id, $view->node->id);
        $this->assertSame(100.0, $view->uptimePercent);
        $this->assertSame(25.0, $view->latestCpu);
        $this->assertSame(8192.0, $view->latestRamMb);
        $this->assertCount(5, $view->charts);
        $this->assertTrue($view->charts[0]->hasData());
    }

    public function test_builds_fleet_dashboard_with_node_summaries(): void
    {
        $online = Node::factory()->forModule('stub')->create([
            'name' => 'Online Node',
            'hostname' => 'online.example.test',
            'config' => [
                'health' => ['state' => NodeHealthState::Online->value],
            ],
        ]);

        $offline = Node::factory()->forModule('stub')->create([
            'name' => 'Offline Node',
            'hostname' => 'offline.example.test',
            'config' => [
                'health' => ['state' => NodeHealthState::Offline->value],
            ],
        ]);

        NodeMetric::factory()->forNode($online)->create([
            'cpu_usage' => 10,
            'ram_usage' => 2048,
            'status' => NodeMetricStatus::Success,
            'collected_at' => now()->subMinutes(10),
        ]);

        NodeMetric::factory()->forNode($offline)->create([
            'cpu_usage' => 80,
            'ram_usage' => 16384,
            'status' => NodeMetricStatus::Success,
            'collected_at' => now()->subMinutes(5),
        ]);

        $dashboard = $this->monitoring->dashboard();

        $this->assertSame(2, $dashboard->totalNodes);
        $this->assertSame(1, $dashboard->onlineCount);
        $this->assertSame(1, $dashboard->offlineCount);
        $this->assertCount(2, $dashboard->nodes);
        $this->assertCount(2, $dashboard->aggregateCharts);
        $this->assertSame(45.0, $dashboard->averageCpu);
    }

    public function test_admin_can_view_monitoring_dashboard(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $node = Node::factory()->forModule('stub')->create([
            'name' => 'Dashboard Node',
            'hostname' => 'dashboard-node.example.test',
        ]);

        NodeMetric::factory()->forNode($node)->create([
            'cpu_usage' => 33.3,
            'ram_usage' => 1024,
            'status' => NodeMetricStatus::Success,
            'collected_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.nodes.monitoring'))
            ->assertOk()
            ->assertSee(__('Node monitoring'))
            ->assertSee('Dashboard Node')
            ->assertSee(__('Average CPU usage'))
            ->assertSee(__('Uptime'))
            ->assertSee('<polyline', false);
    }

    public function test_admin_can_view_node_monitoring_on_detail_page(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $node = Node::factory()->forModule('stub')->create([
            'name' => 'Detail Monitoring Node',
            'hostname' => 'detail-monitoring.example.test',
        ]);

        NodeMetric::factory()->forNode($node)->create([
            'cpu_usage' => 18.0,
            'ram_usage' => 512,
            'disk_usage' => 64,
            'load_average' => 0.75,
            'status' => NodeMetricStatus::Success,
            'collected_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.nodes.show', $node))
            ->assertOk()
            ->assertSee(__('Monitoring'))
            ->assertSee(__('CPU usage'))
            ->assertSee(__('Fleet dashboard'))
            ->assertSee('<polyline', false);
    }

    public function test_navigation_metrics_item_points_to_monitoring_dashboard(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $sections = app(\Core\Admin\Navigation\AdminNavigation::class)->forUser($admin);

        $metricsItem = collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Metrics'));

        $this->assertNotNull($metricsItem);
        $this->assertFalse($metricsItem['placeholder']);
        $this->assertSame(route('admin.nodes.monitoring'), $metricsItem['url']);
    }
}
