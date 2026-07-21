<?php

namespace Core\Nodes\Services;

use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Models\Node;
use Core\Providers\Services\ProviderRegistry;
use Throwable;

class NodeTelemetryService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly NodeHealthCheckService $healthChecks,
        private readonly NodeMetricsCollectionService $metrics,
    ) {
    }

    public function refresh(Node $node): void
    {
        if (! $this->isEligible($node)) {
            return;
        }

        $node = $node->fresh() ?? $node;

        try {
            $this->healthChecks->checkForNode($node);
        } catch (Throwable) {
            // Telemetry must not block admin workflows.
        }

        try {
            $this->metrics->collectForNode($node->fresh() ?? $node);
        } catch (Throwable) {
            // Telemetry must not block admin workflows.
        }
    }

    private function isEligible(Node $node): bool
    {
        if ($node->status === NodeStatus::Disabled) {
            return false;
        }

        $module = trim((string) ($node->module ?? ''));

        if ($module === '') {
            return false;
        }

        return $this->providers->hasNode($module);
    }
}
