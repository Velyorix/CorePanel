<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeAllocationScore;
use Core\Nodes\Enums\NodeClusterStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Illuminate\Support\Collection;

/**
 * Ranks infrastructure nodes for provisioning allocation.
 *
 * Filters eligible nodes, keeps candidates within a utilization band,
 * then distributes assignments with weighted round robin.
 */
class NodeAllocationAlgorithm
{
    public function __construct(
        private readonly NodeLoadBalancingService $loadBalancing,
    ) {
    }

    /**
     * @return Collection<int, Node>
     */
    public function eligibleCandidates(NodeGroup $group, ?string $module): Collection
    {
        $query = Node::query()
            ->selectable()
            ->assignedToGroup((int) $group->id)
            ->withAllocatedCount()
            ->with(['groups' => fn ($builder) => $builder->where('node_groups.id', $group->id)]);

        $module = trim((string) $module);

        if ($module !== '') {
            $query->forModule($module);
        }

        return $query->get()->filter(fn (Node $node): bool => $this->isEligible($node));
    }

    public function selectBest(NodeGroup $group, ?string $module): ?Node
    {
        $candidates = $this->eligibleCandidates($group, $module);

        if ($candidates->isEmpty()) {
            return null;
        }

        return $this->pick($candidates, $this->groupScope($group, $module));
    }

    public function selectBestExcluding(NodeGroup $group, ?string $module, int $excludeNodeId): ?Node
    {
        $candidates = $this->eligibleCandidates($group, $module)
            ->reject(fn (Node $node): bool => (int) $node->id === $excludeNodeId);

        if ($candidates->isEmpty()) {
            return null;
        }

        return $this->pick($candidates, $this->groupScope($group, $module).':exclude:'.$excludeNodeId);
    }

    public function selectBestFromPool(?string $module, int $excludeNodeId): ?Node
    {
        $query = Node::query()
            ->selectable()
            ->withAllocatedCount();

        $module = trim((string) $module);

        if ($module !== '') {
            $query->forModule($module);
        }

        $candidates = $query->get()
            ->reject(fn (Node $node): bool => (int) $node->id === $excludeNodeId)
            ->filter(fn (Node $node): bool => $this->isEligible($node));

        return $this->pick($candidates, $this->poolScope($module).':exclude:'.$excludeNodeId);
    }

    /**
     * Prefer healthy peers that share an active HA cluster with the failed node.
     */
    public function selectBestClusterPeer(Node $failedNode, ?string $module): ?Node
    {
        $failedNode->loadMissing(['clusters' => fn ($query) => $query->active()]);

        $clusterIds = $failedNode->clusters
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($clusterIds === []) {
            return null;
        }

        $query = Node::query()
            ->selectable()
            ->whereKeyNot($failedNode->id)
            ->whereHas('clusters', function ($builder) use ($clusterIds): void {
                $builder->whereIn('node_clusters.id', $clusterIds)
                    ->where('node_clusters.status', NodeClusterStatus::Active->value);
            })
            ->withAllocatedCount()
            ->with(['clusters' => fn ($builder) => $builder->whereIn('node_clusters.id', $clusterIds)]);

        $module = trim((string) $module);

        if ($module !== '') {
            $query->forModule($module);
        }

        $candidates = $query->get()->filter(fn (Node $node): bool => $this->isEligible($node));

        if ($candidates->isEmpty()) {
            return null;
        }

        return $this->pick(
            $candidates,
            'cluster:'.$failedNode->id.':'.$this->normalizedModule($module),
        );
    }

    /**
     * @param  Collection<int, Node>  $candidates
     */
    private function pick(Collection $candidates, string $scope): ?Node
    {
        return $this->loadBalancing->select(
            $candidates,
            $scope,
            fn (Node $node): float => $this->utilizationScore($node),
        );
    }

    private function groupScope(NodeGroup $group, ?string $module): string
    {
        return 'group:'.$group->id.':'.$this->normalizedModule($module);
    }

    private function poolScope(?string $module): string
    {
        return 'pool:'.$this->normalizedModule($module);
    }

    private function normalizedModule(?string $module): string
    {
        $module = trim((string) $module);

        return $module === '' ? 'any' : $module;
    }

    public function isEligible(Node $node): bool
    {
        if (! $node->isSelectable()) {
            return false;
        }

        if (! $node->isHealthEligible()) {
            return false;
        }

        if (! $node->hasCapacity()) {
            return false;
        }

        if ($this->requiresCredentials() && ! $node->hasConfiguredCredentials()) {
            return false;
        }

        return true;
    }

    public function utilizationScore(Node $node): float
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

    /**
     * @param  Collection<int, Node>  $candidates
     * @return Collection<int, NodeAllocationScore>
     */
    public function rank(Collection $candidates, NodeGroup $group): Collection
    {
        return $candidates
            ->map(fn (Node $node): NodeAllocationScore => new NodeAllocationScore(
                node: $node,
                utilization: $this->utilizationScore($node),
                allocatedServices: $node->allocatedServicesCount(),
                sortOrder: $this->groupSortOrder($node, $group),
            ))
            ->sort(function (NodeAllocationScore $left, NodeAllocationScore $right): int {
                return $left->utilization <=> $right->utilization
                    ?: $left->allocatedServices <=> $right->allocatedServices
                    ?: $left->sortOrder <=> $right->sortOrder
                    ?: $left->node->id <=> $right->node->id;
            })
            ->values();
    }

    private function groupSortOrder(Node $node, NodeGroup $group): int
    {
        $assigned = $node->groups->firstWhere('id', $group->id);

        if ($assigned !== null && $assigned->pivot !== null) {
            return (int) $assigned->pivot->sort_order;
        }

        if ((int) $node->node_group_id === (int) $group->id) {
            return (int) $node->sort_order;
        }

        return (int) $node->sort_order;
    }

    private function requiresCredentials(): bool
    {
        return (bool) config('corepanel.nodes.allocation.require_credentials', false);
    }
}
