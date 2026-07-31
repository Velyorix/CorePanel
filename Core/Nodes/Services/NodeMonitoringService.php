<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeMonitoringChartSeries;
use Core\Nodes\DataTransferObjects\NodeMonitoringDashboard;
use Core\Nodes\DataTransferObjects\NodeMonitoringNodeSummary;
use Core\Nodes\DataTransferObjects\NodeMonitoringView;
use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Enums\NodeMetricStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeHealthCheck;
use Core\Nodes\Models\NodeMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NodeMonitoringService
{
    public function dashboard(?int $historyHours = null): NodeMonitoringDashboard
    {
        $historyHours = $this->resolveHistoryHours($historyHours);
        $since = now()->subHours($historyHours);

        $nodes = Node::query()
            ->with('group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $summaries = $nodes
            ->map(fn (Node $node): NodeMonitoringNodeSummary => $this->summarizeNode($node, $since))
            ->values()
            ->all();

        $healthCounts = [
            'online' => 0,
            'degraded' => 0,
            'offline' => 0,
        ];

        foreach ($nodes as $node) {
            match ($node->healthState()) {
                NodeHealthState::Online => $healthCounts['online']++,
                NodeHealthState::Degraded => $healthCounts['degraded']++,
                NodeHealthState::Offline => $healthCounts['offline']++,
                default => null,
            };
        }

        $latestMetrics = $this->successfulMetricsSince($since);

        return new NodeMonitoringDashboard(
            totalNodes: $nodes->count(),
            onlineCount: $healthCounts['online'],
            degradedCount: $healthCounts['degraded'],
            offlineCount: $healthCounts['offline'],
            averageCpu: $this->averageMetricValue($latestMetrics, 'cpu_usage'),
            averageRamMb: $this->averageMetricValue($latestMetrics, 'ram_usage'),
            nodes: $summaries,
            aggregateCharts: [
                $this->aggregateSeries(
                    key: 'fleet_cpu',
                    label: __('Average CPU usage'),
                    unit: '%',
                    metrics: $latestMetrics,
                    attribute: 'cpu_usage',
                    since: $since,
                ),
                $this->aggregateSeries(
                    key: 'fleet_ram',
                    label: __('Average RAM usage'),
                    unit: 'MB',
                    metrics: $latestMetrics,
                    attribute: 'ram_usage',
                    since: $since,
                ),
            ],
            historyHours: $historyHours,
        );
    }

    public function forNode(Node $node, ?int $historyHours = null): NodeMonitoringView
    {
        $historyHours = $this->resolveHistoryHours($historyHours);
        $since = now()->subHours($historyHours);

        $metrics = NodeMetric::query()
            ->where('node_id', $node->id)
            ->where('collected_at', '>=', $since)
            ->where('status', NodeMetricStatus::Success)
            ->orderBy('collected_at')
            ->get();

        $healthChecks = NodeHealthCheck::query()
            ->where('node_id', $node->id)
            ->where('checked_at', '>=', $since)
            ->orderBy('checked_at')
            ->get();

        $latest = $metrics->last();

        return new NodeMonitoringView(
            node: $node,
            uptimePercent: $this->uptimePercent($healthChecks),
            latestCpu: $latest?->cpu_usage !== null ? (float) $latest->cpu_usage : null,
            latestRamMb: $latest?->ram_usage !== null ? (float) $latest->ram_usage : null,
            latestDiskGb: $latest?->disk_usage !== null ? (float) $latest->disk_usage : null,
            latestLoad: $latest?->load_average !== null ? (float) $latest->load_average : null,
            latestNetworkIn: $latest?->network_in !== null ? (float) $latest->network_in : null,
            latestNetworkOut: $latest?->network_out !== null ? (float) $latest->network_out : null,
            charts: [
                $this->metricSeries('cpu', __('CPU usage'), '%', $metrics, 'cpu_usage'),
                $this->metricSeries('ram', __('RAM usage'), 'MB', $metrics, 'ram_usage'),
                $this->metricSeries('disk', __('Disk usage'), 'GB', $metrics, 'disk_usage'),
                $this->networkSeries($metrics),
                $this->metricSeries('load', __('Load average'), '', $metrics, 'load_average'),
            ],
            historyHours: $historyHours,
        );
    }

    private function summarizeNode(Node $node, Carbon $since): NodeMonitoringNodeSummary
    {
        $latest = NodeMetric::query()
            ->where('node_id', $node->id)
            ->where('collected_at', '>=', $since)
            ->where('status', NodeMetricStatus::Success)
            ->orderByDesc('collected_at')
            ->first();

        $healthChecks = NodeHealthCheck::query()
            ->where('node_id', $node->id)
            ->where('checked_at', '>=', $since)
            ->orderBy('checked_at')
            ->get();

        return new NodeMonitoringNodeSummary(
            node: $node,
            uptimePercent: $this->uptimePercent($healthChecks),
            latestCpu: $latest?->cpu_usage !== null ? (float) $latest->cpu_usage : null,
            latestRamMb: $latest?->ram_usage !== null ? (float) $latest->ram_usage : null,
            latestDiskGb: $latest?->disk_usage !== null ? (float) $latest->disk_usage : null,
            latestLoad: $latest?->load_average !== null ? (float) $latest->load_average : null,
        );
    }

    /**
     * @param  Collection<int, NodeHealthCheck>  $checks
     */
    private function uptimePercent(Collection $checks): ?float
    {
        $eligible = $checks->reject(
            fn (NodeHealthCheck $check): bool => $check->state === NodeHealthState::Skipped,
        );

        if ($eligible->isEmpty()) {
            return null;
        }

        $score = 0.0;

        foreach ($eligible as $check) {
            $score += match ($check->state) {
                NodeHealthState::Online => 1.0,
                NodeHealthState::Degraded => 0.5,
                default => 0.0,
            };
        }

        return round(($score / $eligible->count()) * 100, 1);
    }

    /**
     * @param  Collection<int, NodeMetric>  $metrics
     */
    private function metricSeries(
        string $key,
        string $label,
        string $unit,
        Collection $metrics,
        string $attribute,
    ): NodeMonitoringChartSeries {
        $values = [];
        $labels = [];

        foreach ($metrics as $metric) {
            $value = $metric->{$attribute};
            $values[] = $value !== null ? (float) $value : null;
            $labels[] = $metric->collected_at?->format('H:i') ?? '';
        }

        return new NodeMonitoringChartSeries(
            key: $key,
            label: $label,
            unit: $unit,
            values: $values,
            labels: $labels,
        );
    }

    /**
     * @param  Collection<int, NodeMetric>  $metrics
     */
    private function networkSeries(Collection $metrics): NodeMonitoringChartSeries
    {
        $values = [];
        $labels = [];

        foreach ($metrics as $metric) {
            $in = $metric->network_in !== null ? (float) $metric->network_in : 0.0;
            $out = $metric->network_out !== null ? (float) $metric->network_out : 0.0;
            $values[] = max($in, $out) > 0 ? max($in, $out) : null;
            $labels[] = $metric->collected_at?->format('H:i') ?? '';
        }

        return new NodeMonitoringChartSeries(
            key: 'network',
            label: __('Network throughput'),
            unit: 'Mbps',
            values: $values,
            labels: $labels,
        );
    }

    /**
     * @param  Collection<int, NodeMetric>  $metrics
     */
    private function aggregateSeries(
        string $key,
        string $label,
        string $unit,
        Collection $metrics,
        string $attribute,
        Carbon $since,
    ): NodeMonitoringChartSeries {
        if ($metrics->isEmpty()) {
            return new NodeMonitoringChartSeries($key, $label, $unit, [], []);
        }

        $bucketMinutes = max(1, (int) config('corepanel.nodes.monitoring.bucket_minutes', 15));
        $values = [];
        $labels = [];

        $cursor = $since->copy()->startOfMinute();
        $end = now()->startOfMinute();

        while ($cursor->lessThanOrEqualTo($end)) {
            $bucketEnd = $cursor->copy()->addMinutes($bucketMinutes);
            $bucketMetrics = $metrics->filter(
                fn (NodeMetric $metric): bool => $metric->collected_at !== null
                    && $metric->collected_at->greaterThanOrEqualTo($cursor)
                    && $metric->collected_at->lessThan($bucketEnd),
            );

            if ($bucketMetrics->isNotEmpty()) {
                $average = $bucketMetrics
                    ->pluck($attribute)
                    ->filter(static fn (mixed $value): bool => $value !== null)
                    ->avg();

                $values[] = $average !== null ? (float) $average : null;
            } else {
                $values[] = null;
            }

            $labels[] = $cursor->format('H:i');
            $cursor = $bucketEnd;
        }

        return new NodeMonitoringChartSeries(
            key: $key,
            label: $label,
            unit: $unit,
            values: $values,
            labels: $labels,
        );
    }

    /**
     * @return Collection<int, NodeMetric>
     */
    private function successfulMetricsSince(Carbon $since): Collection
    {
        return NodeMetric::query()
            ->where('collected_at', '>=', $since)
            ->where('status', NodeMetricStatus::Success)
            ->orderBy('collected_at')
            ->get();
    }

    /**
     * @param  Collection<int, NodeMetric>  $metrics
     */
    private function averageMetricValue(Collection $metrics, string $attribute): ?float
    {
        $values = $metrics
            ->pluck($attribute)
            ->filter(static fn (mixed $value): bool => $value !== null)
            ->map(static fn (mixed $value): float => (float) $value);

        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->avg(), 2);
    }

    private function resolveHistoryHours(?int $historyHours): int
    {
        $hours = $historyHours ?? (int) config('corepanel.nodes.monitoring.history_hours', 24);

        return max(1, min($hours, 168));
    }
}
