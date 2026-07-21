<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeCapacityUsage;
use Core\Nodes\Models\Node;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Illuminate\Support\Carbon;

class NodeCapacityService
{
    public function applyResources(Node $node, NodeResourcesData $resources, string $source = 'sync'): Node
    {
        $usage = array_filter([
            'cpu_cores' => $resources->cpuUsage !== null ? (int) round($resources->cpuUsage) : null,
            'ram_mb' => $resources->ramUsage !== null ? (int) round($resources->ramUsage) : null,
            'disk_gb' => $resources->diskUsage !== null ? (int) round($resources->diskUsage) : null,
            'services' => $resources->currentServices,
            'bandwidth_in_mbps' => $resources->networkIn,
            'bandwidth_out_mbps' => $resources->networkOut,
            'load_average' => $resources->loadAverage,
            'capacity_available' => $resources->capacityAvailable,
        ], static fn (mixed $value): bool => $value !== null);

        $syncedAt = now()->toIso8601String();
        $usage['synced_at'] = $syncedAt;
        $usage['source'] = $source;

        $config = is_array($node->config) ? $node->config : [];
        $capacity = is_array($config['capacity'] ?? null) ? $config['capacity'] : [];
        $legacyAllocated = is_array($capacity['allocated'] ?? null) ? $capacity['allocated'] : [];

        foreach (['cpu_cores', 'ram_mb', 'disk_gb'] as $key) {
            if (array_key_exists($key, $usage)) {
                $legacyAllocated[$key] = $usage[$key];
            }
        }

        if (array_key_exists('services', $usage)) {
            $legacyAllocated['services'] = $usage['services'];
        }

        $capacity['usage'] = $usage;
        $capacity['allocated'] = $legacyAllocated;
        $capacity['available'] = $resources->capacityAvailable;
        $capacity['synced_at'] = $syncedAt;
        $capacity['source'] = $source;

        $config['capacity'] = $capacity;

        $node->forceFill(['config' => $config])->save();

        return $node->fresh() ?? $node;
    }

    public function usage(Node $node): NodeCapacityUsage
    {
        return NodeCapacityUsage::fromNode($node);
    }

    public function isUsageFresh(Node $node, ?int $maxAgeSeconds = null): bool
    {
        $syncedAt = $this->usage($node)->syncedAt;

        if ($syncedAt === null || $syncedAt === '') {
            return false;
        }

        $maxAge = $maxAgeSeconds ?? (int) config('corepanel.nodes.capacity.stale_after_seconds', 600);

        try {
            return Carbon::parse($syncedAt)->greaterThanOrEqualTo(now()->subSeconds(max(1, $maxAge)));
        } catch (\Throwable) {
            return false;
        }
    }
}
