<?php

namespace Core\Nodes\Services;

use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Models\Node;

class NodeOverloadService
{
    public function enabled(): bool
    {
        return (bool) config('corepanel.nodes.overload.enabled', true);
    }

    public function isOverloaded(Node $node, bool $fallbackPool = false): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if ($node->status->isSelectable() === false) {
            return true;
        }

        if ($this->healthBlocksAllocation($node, $fallbackPool)) {
            return true;
        }

        if ($this->capacityUnavailable($node)) {
            return true;
        }

        if ($this->utilizationExceeded($node, $fallbackPool)) {
            return true;
        }

        if ($this->serviceCapacityExceeded($node, $fallbackPool)) {
            return true;
        }

        return false;
    }

    public function isFallbackReceiver(Node $node): bool
    {
        $allocation = is_array($node->config['allocation'] ?? null)
            ? $node->config['allocation']
            : [];

        if ((bool) ($allocation['is_fallback'] ?? false)) {
            return true;
        }

        return in_array((int) $node->id, $this->globalFallbackNodeIds(), true);
    }

    /**
     * @return list<int>
     */
    public function globalFallbackNodeIds(): array
    {
        $configured = config('corepanel.nodes.overload.fallback_node_ids', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (mixed $id): int => max(0, (int) $id),
            $configured,
        )));
    }

    public function deferDelaySeconds(): int
    {
        return max(
            5,
            (int) config('corepanel.nodes.overload.queue_delay_seconds', 60),
        );
    }

    private function healthBlocksAllocation(Node $node, bool $fallbackPool): bool
    {
        if (! (bool) config('corepanel.nodes.overload.block_degraded', true)) {
            return false;
        }

        if ($fallbackPool && (bool) config('corepanel.nodes.overload.allow_degraded_fallback', true)) {
            return $node->healthState() === NodeHealthState::Offline;
        }

        return in_array($node->healthState(), [
            NodeHealthState::Degraded,
            NodeHealthState::Offline,
        ], true);
    }

    private function capacityUnavailable(Node $node): bool
    {
        if (! (bool) config('corepanel.nodes.overload.respect_capacity_available_flag', true)) {
            return false;
        }

        $available = $node->capacityUsage()->capacityAvailable;

        return $available === false;
    }

    private function utilizationExceeded(Node $node, bool $fallbackPool): bool
    {
        $threshold = $fallbackPool
            ? (float) config('corepanel.nodes.overload.fallback_utilization_threshold', 0.95)
            : (float) config('corepanel.nodes.overload.utilization_threshold', 0.85);

        return $this->utilizationScore($node) >= $threshold;
    }

    private function utilizationScore(Node $node): float
    {
        $ratios = [];

        if ($node->max_services !== null && $node->max_services > 0) {
            $ratios[] = $node->allocatedServicesCount() / $node->max_services;
        }

        $allocated = $node->allocatedResources();

        if ($node->max_cpu_cores !== null && $node->max_cpu_cores > 0) {
            $ratios[] = $allocated['cpu_cores'] / $node->max_cpu_cores;
        }

        if ($node->max_ram_mb !== null && $node->max_ram_mb > 0) {
            $ratios[] = $allocated['ram_mb'] / $node->max_ram_mb;
        }

        if ($node->max_disk_gb !== null && $node->max_disk_gb > 0) {
            $ratios[] = $allocated['disk_gb'] / $node->max_disk_gb;
        }

        if ($node->max_bandwidth_mbps !== null && $node->max_bandwidth_mbps > 0) {
            $usage = $node->capacityUsage();
            $ratios[] = $usage->peakBandwidthMbps() / $node->max_bandwidth_mbps;
        }

        if ($ratios === []) {
            return (float) $node->allocatedServicesCount();
        }

        return max($ratios);
    }

    private function serviceCapacityExceeded(Node $node, bool $fallbackPool): bool
    {
        if ($node->max_services === null || $node->max_services <= 0) {
            return false;
        }

        $ratio = $node->allocatedServicesCount() / $node->max_services;
        $threshold = $fallbackPool
            ? (float) config('corepanel.nodes.overload.fallback_service_fill_threshold', 0.98)
            : (float) config('corepanel.nodes.overload.service_fill_threshold', 0.95);

        return $ratio >= $threshold;
    }
}
