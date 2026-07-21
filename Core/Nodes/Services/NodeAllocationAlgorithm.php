<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeAllocationScore;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Illuminate\Support\Collection;

/**
 * Ranks infrastructure nodes for provisioning allocation.
 *
 * Strategy: filter eligible nodes, then pick the lowest utilization score
 * (services + CPU/RAM/disk ratios), with sort order as tiebreaker.
 */
class NodeAllocationAlgorithm
{
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

        return $this->rank($candidates, $group)->first()?->node;
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
