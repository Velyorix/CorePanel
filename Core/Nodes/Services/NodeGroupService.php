<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeGroupData;
use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Models\NodeGroupRelation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NodeGroupService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     type?: NodeGroupType|null,
     *     status?: NodeGroupStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'sort_order';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'key', 'type', 'status', 'location', 'sort_order', 'created_at'], true)) {
            $sort = 'sort_order';
        }

        $query = NodeGroup::query()
            ->withCount(['assignedNodes', 'provisioningRules']);

        if ($type instanceof NodeGroupType) {
            $query->where('type', $type->value);
        }

        if ($status instanceof NodeGroupStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($search, $term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('key', 'like', $term)
                    ->orWhere('location', 'like', $term)
                    ->orWhere('description', 'like', $term);

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(NodeGroupData $data): NodeGroup
    {
        $this->assertKeyIsUnique($data->key);

        return DB::transaction(function () use ($data): NodeGroup {
            $group = NodeGroup::query()->create($data->toAttributes());

            if ($data->nodeIdsProvided) {
                $this->syncNodeAssignments($group, $data->nodeIds ?? []);
            }

            return $group->fresh(['assignedNodes']) ?? $group;
        });
    }

    public function update(NodeGroup $group, NodeGroupData $data): NodeGroup
    {
        $this->assertKeyIsUnique($data->key, $group->id);

        return DB::transaction(function () use ($group, $data): NodeGroup {
            $group->update($data->toAttributes());

            if ($data->nodeIdsProvided) {
                $this->syncNodeAssignments($group->fresh() ?? $group, $data->nodeIds ?? []);
            }

            return $group->fresh(['assignedNodes']) ?? $group;
        });
    }

    public function delete(NodeGroup $group): void
    {
        if ($group->provisioningRules()->exists()) {
            throw new InvalidArgumentException('Cannot delete a node group linked to products.');
        }

        $group->delete();
    }

    public function findByKey(string $key): ?NodeGroup
    {
        return NodeGroup::query()->where('key', $key)->first();
    }

    public function findActiveByKey(string $key): ?NodeGroup
    {
        return NodeGroup::query()->active()->where('key', $key)->first();
    }

    /**
     * @param  list<int>  $nodeIds
     */
    public function syncNodeAssignments(NodeGroup $group, array $nodeIds): void
    {
        $nodeIds = array_values(array_unique(array_map(intval(...), $nodeIds)));

        if ($nodeIds !== []) {
            $existing = Node::query()->whereIn('id', $nodeIds)->count();

            if ($existing !== count($nodeIds)) {
                throw new InvalidArgumentException('One or more selected servers do not exist.');
            }
        }

        NodeGroupRelation::query()
            ->where('node_group_id', $group->id)
            ->when($nodeIds !== [], fn ($query) => $query->whereNotIn('node_id', $nodeIds))
            ->delete();

        foreach ($nodeIds as $index => $nodeId) {
            $node = Node::query()->findOrFail($nodeId);
            $isPrimary = $node->node_group_id === null || $node->node_group_id === $group->id;

            if ($isPrimary) {
                $node->update(['node_group_id' => $group->id]);
            }

            NodeGroupRelation::query()->updateOrCreate(
                [
                    'node_id' => $nodeId,
                    'node_group_id' => $group->id,
                ],
                [
                    'is_primary' => $isPrimary,
                    'sort_order' => $index,
                ],
            );
        }

        Node::query()
            ->where('node_group_id', $group->id)
            ->when($nodeIds !== [], fn ($query) => $query->whereNotIn('id', $nodeIds))
            ->update(['node_group_id' => null]);
    }

    private function assertKeyIsUnique(string $key, ?int $ignoreId = null): void
    {
        $query = NodeGroup::query()->where('key', $key);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('A node group with this key already exists.');
        }
    }
}
