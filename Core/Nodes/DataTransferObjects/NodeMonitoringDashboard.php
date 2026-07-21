<?php

namespace Core\Nodes\DataTransferObjects;

final readonly class NodeMonitoringDashboard
{
    /**
     * @param  list<NodeMonitoringNodeSummary>  $nodes
     * @param  list<NodeMonitoringChartSeries>  $aggregateCharts
     */
    public function __construct(
        public int $totalNodes,
        public int $onlineCount,
        public int $degradedCount,
        public int $offlineCount,
        public ?float $averageCpu,
        public ?float $averageRamMb,
        public array $nodes,
        public array $aggregateCharts,
        public int $historyHours,
    ) {
    }
}
