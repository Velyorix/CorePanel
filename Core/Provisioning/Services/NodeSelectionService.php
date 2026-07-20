<?php

namespace Core\Provisioning\Services;

use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Provisioning\DataTransferObjects\NodeSelectionResult;
use Core\Provisioning\Exceptions\NoEligibleNodeException;
use Core\Services\Models\Service;
use Illuminate\Database\Eloquent\Collection;

/**
 * Selects and assigns an infrastructure node before provider execution.
 *
 * Strategy: resolve product node group → filter selectable nodes by module
 * and remaining capacity → pick the least allocated node.
 */
class NodeSelectionService
{
    public function resolveGroup(Service $service): ?NodeGroup
    {
        $service->loadMissing('product.provisioningRules.nodeGroup');

        $rules = $service->product?->provisioningRules;

        if ($rules === null || ! $rules->hasNodeGroup()) {
            return null;
        }

        if ($rules->nodeGroup instanceof NodeGroup) {
            return $rules->nodeGroup;
        }

        if ($rules->node_group_id !== null) {
            return NodeGroup::query()->find($rules->node_group_id);
        }

        $key = trim((string) $rules->node_group_key);

        if ($key === '') {
            return null;
        }

        return NodeGroup::query()->where('key', $key)->first();
    }

    public function selectForService(Service $service): NodeSelectionResult
    {
        $service->loadMissing('product.provisioningRules.nodeGroup');

        $rules = $service->product?->provisioningRules;
        $group = $this->resolveGroup($service);

        if ($rules !== null && $rules->hasNodeGroup() && $group === null) {
            $reference = filled($rules->node_group_key)
                ? (string) $rules->node_group_key
                : (string) $rules->node_group_id;

            throw NoEligibleNodeException::forMissingGroup($reference);
        }

        if ($group === null) {
            if ($service->node_id !== null) {
                $existing = Node::query()->find($service->node_id);

                if ($existing !== null && $existing->isSelectable()) {
                    return new NodeSelectionResult(
                        node: $existing,
                        connection: $existing->toConnectionRequest(),
                    );
                }
            }

            return new NodeSelectionResult;
        }

        if ($group->status !== NodeGroupStatus::Active) {
            if ($this->requiresNodeWhenGroupAssigned()) {
                throw NoEligibleNodeException::forGroup($group->key, $service->module);
            }

            return new NodeSelectionResult(group: $group);
        }

        if ($service->node_id !== null) {
            $existing = Node::query()
                ->selectable()
                ->inGroup((int) $group->id)
                ->whereKey($service->node_id)
                ->first();

            if ($existing !== null && $this->moduleMatches($existing, $service->module) && $existing->hasCapacity()) {
                return new NodeSelectionResult(
                    group: $group,
                    node: $existing,
                    connection: $existing->toConnectionRequest(),
                );
            }
        }

        $candidates = $this->candidateNodes($group, $service->module);
        $node = $candidates->first(fn (Node $candidate): bool => $candidate->hasCapacity());

        if ($node === null) {
            if ($this->requiresNodeWhenGroupAssigned()) {
                throw NoEligibleNodeException::forGroup($group->key, $service->module);
            }

            return new NodeSelectionResult(group: $group);
        }

        return new NodeSelectionResult(
            group: $group,
            node: $node,
            connection: $node->toConnectionRequest(),
        );
    }

    /**
     * Persist the selected node_id on the service and return the selection.
     */
    public function assignToService(Service $service): NodeSelectionResult
    {
        $selection = $this->selectForService($service);

        if ($selection->nodeId() === null) {
            return $selection;
        }

        if ((int) $service->node_id === (int) $selection->nodeId()) {
            return $selection;
        }

        $service->forceFill(['node_id' => $selection->nodeId()])->save();

        return $selection;
    }

    /**
     * @return Collection<int, Node>
     */
    private function candidateNodes(NodeGroup $group, ?string $module): Collection
    {
        $query = Node::query()
            ->selectable()
            ->inGroup((int) $group->id)
            ->withAllocatedCount()
            ->orderBy('allocated_services_count')
            ->orderBy('sort_order')
            ->orderBy('id');

        $module = trim((string) $module);

        if ($module !== '') {
            $query->forModule($module);
        }

        return $query->get();
    }

    private function moduleMatches(Node $node, ?string $module): bool
    {
        $module = trim((string) $module);

        if ($module === '' || $node->module === null || $node->module === '') {
            return true;
        }

        return $node->module === $module;
    }

    private function requiresNodeWhenGroupAssigned(): bool
    {
        return (bool) config('corepanel.provisioning.require_node_for_assigned_group', true);
    }
}
