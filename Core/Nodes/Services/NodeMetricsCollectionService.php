<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeMetricsCollectionResult;
use Core\Nodes\Enums\NodeMetricStatus;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeMetric;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\Services\ProviderRegistry;
use Illuminate\Support\Collection;

class NodeMetricsCollectionService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly NodeCapacityService $capacity,
    ) {
    }

    public function collectAll(): NodeMetricsCollectionResult
    {
        if (! (bool) config('corepanel.nodes.metrics.enabled', true)) {
            return new NodeMetricsCollectionResult;
        }

        $collected = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->monitorableNodes() as $node) {
            $metric = $this->collectForNode($node);

            match ($metric->status) {
                NodeMetricStatus::Success => $collected++,
                NodeMetricStatus::Failed => $failed++,
                NodeMetricStatus::Skipped => $skipped++,
            };
        }

        return new NodeMetricsCollectionResult(
            collected: $collected,
            failed: $failed,
            skipped: $skipped,
        );
    }

    public function collectForNode(Node $node): NodeMetric
    {
        $collectedAt = now();

        if ($node->status === NodeStatus::Disabled) {
            return $this->storeMetric($node, NodeMetricStatus::Skipped, $collectedAt, null, [
                'reason' => 'node_disabled',
            ]);
        }

        $module = trim((string) ($node->module ?? ''));

        if ($module === '') {
            return $this->storeMetric($node, NodeMetricStatus::Skipped, $collectedAt, null, [
                'reason' => 'missing_module',
            ]);
        }

        if (! $this->providers->hasNode($module)) {
            return $this->storeMetric($node, NodeMetricStatus::Skipped, $collectedAt, null, [
                'reason' => 'unknown_module',
                'module' => $module,
            ]);
        }

        $response = $this->providers->node($module)->getResources($node->toConnectionRequest());

        if (! $response->status->isSuccessful()) {
            return $this->storeMetric($node, NodeMetricStatus::Failed, $collectedAt, null, array_filter([
                'message' => $response->message,
                'payload' => $response->payload !== [] ? $response->payload : null,
            ], static fn (mixed $value): bool => $value !== null));
        }

        $metric = $this->storeMetric(
            node: $node,
            status: NodeMetricStatus::Success,
            collectedAt: $collectedAt,
            resources: $response->resources,
            payload: $response->payload !== [] ? $response->payload : null,
        );

        $this->capacity->applyResources($node, $response->resources, 'metrics');

        return $metric;
    }

    /**
     * @return Collection<int, Node>
     */
    private function monitorableNodes(): Collection
    {
        return Node::query()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function storeMetric(
        Node $node,
        NodeMetricStatus $status,
        \Illuminate\Support\Carbon $collectedAt,
        ?NodeResourcesData $resources = null,
        ?array $payload = null,
    ): NodeMetric {
        $metric = new NodeMetric([
            'node_id' => $node->id,
            'current_services' => $resources?->currentServices,
            'max_services' => $resources?->maxServices,
            'cpu_usage' => $resources?->cpuUsage,
            'ram_usage' => $resources?->ramUsage,
            'disk_usage' => $resources?->diskUsage,
            'network_in' => $resources?->networkIn,
            'network_out' => $resources?->networkOut,
            'load_average' => $resources?->loadAverage,
            'capacity_available' => $resources?->capacityAvailable,
            'status' => $status,
            'payload' => $payload,
            'collected_at' => $collectedAt,
            'created_at' => now(),
        ]);

        $metric->save();

        return $metric;
    }
}
