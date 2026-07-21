<?php

namespace Core\Nodes\DataTransferObjects;

use Core\Nodes\Models\Node;

final readonly class NodeMonitoringView
{
    /**
     * @param  list<NodeMonitoringChartSeries>  $charts
     */
    public function __construct(
        public Node $node,
        public ?float $uptimePercent,
        public ?float $latestCpu,
        public ?float $latestRamMb,
        public ?float $latestDiskGb,
        public ?float $latestLoad,
        public ?float $latestNetworkIn,
        public ?float $latestNetworkOut,
        public array $charts,
        public int $historyHours,
    ) {
    }
}
