<?php

namespace Core\Automation\Services;

use Core\Nodes\Services\NodeMonitoringService;
use Illuminate\Support\Facades\Log;

class SystemHealthReportService
{
    public function __construct(
        private readonly NodeMonitoringService $monitoring,
    ) {
    }

    /**
     * @return array{
     *     nodes_total: int,
     *     online: int,
     *     degraded: int,
     *     offline: int
     * }
     */
    public function generateDaily(): array
    {
        $dashboard = $this->monitoring->dashboard();

        $report = [
            'nodes_total' => $dashboard->totalNodes,
            'online' => $dashboard->onlineCount,
            'degraded' => $dashboard->degradedCount,
            'offline' => $dashboard->offlineCount,
        ];

        Log::info('automation.report.system_health', $report);

        return $report;
    }
}
