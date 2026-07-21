<?php

namespace Core\Provisioning\Services;

use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Services\NodeAllocationAlgorithm;
use Core\Provisioning\DataTransferObjects\NodeSelectionResult;
use Core\Provisioning\Exceptions\NoEligibleNodeException;
use Core\Services\Models\Service;

/**
 * Selects and assigns an infrastructure node before provider execution.
 */
class NodeSelectionService
{
    public function __construct(
        private readonly NodeAllocationAlgorithm $allocation,
    ) {
    }

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

                if ($existing !== null && $this->allocation->isEligible($existing)) {
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
                ->assignedToGroup((int) $group->id)
                ->whereKey($service->node_id)
                ->first();

            if (
                $existing !== null
                && $this->moduleMatches($existing, $service->module)
                && $this->allocation->isEligible($existing)
            ) {
                return new NodeSelectionResult(
                    group: $group,
                    node: $existing,
                    connection: $existing->toConnectionRequest(),
                );
            }
        }

        $node = $this->allocation->selectBest($group, $service->module);

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

    public function selectReplacement(Service $service, int $excludeNodeId): NodeSelectionResult
    {
        $service->loadMissing('product.provisioningRules.nodeGroup');

        $group = $this->resolveGroup($service);
        $failedNode = Node::query()->find($excludeNodeId);

        if (
            $failedNode !== null
            && (bool) config('corepanel.nodes.clusters.prefer_peers_on_failover', true)
        ) {
            $peer = $this->allocation->selectBestClusterPeer($failedNode, $service->module);

            if ($peer !== null) {
                return new NodeSelectionResult(
                    group: $group,
                    node: $peer,
                    connection: $peer->toConnectionRequest(),
                );
            }
        }

        if ($group !== null && $group->status === NodeGroupStatus::Active) {
            $node = $this->allocation->selectBestExcluding($group, $service->module, $excludeNodeId);

            if ($node === null) {
                throw NoEligibleNodeException::forServiceReplacement((int) $service->id);
            }

            return new NodeSelectionResult(
                group: $group,
                node: $node,
                connection: $node->toConnectionRequest(),
            );
        }

        $node = $this->allocation->selectBestFromPool($service->module, $excludeNodeId);

        if ($node === null) {
            throw NoEligibleNodeException::forServiceReplacement((int) $service->id);
        }

        return new NodeSelectionResult(
            node: $node,
            connection: $node->toConnectionRequest(),
        );
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
