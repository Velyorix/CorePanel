<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeData;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Models\NodeGroupRelation;
use Core\Nodes\Services\NodeCredentialsService;
use Core\Providers\Services\ProviderRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NodeService
{
    private const RELATIONS = ['group', 'groups'];

    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly NodeCredentialsService $credentials,
    ) {
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     type?: NodeType|null,
     *     status?: NodeStatus|null,
     *     module?: string|null,
     *     node_group_id?: int|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $module = $filters['module'] ?? null;
        $groupId = $filters['node_group_id'] ?? null;
        $sort = $filters['sort'] ?? 'sort_order';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'hostname', 'type', 'status', 'module', 'sort_order', 'created_at'], true)) {
            $sort = 'sort_order';
        }

        $query = Node::query()
            ->with('group')
            ->withAllocatedCount();

        if ($type instanceof NodeType) {
            $query->where('type', $type->value);
        }

        if ($status instanceof NodeStatus) {
            $query->where('status', $status->value);
        }

        if (filled($module)) {
            $query->where('module', $module);
        }

        if ($groupId !== null) {
            $query->where('node_group_id', (int) $groupId);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($search, $term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('hostname', 'like', $term)
                    ->orWhere('module', 'like', $term)
                    ->orWhere('ip_address', 'like', $term);

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

    public function find(int $id): ?Node
    {
        return Node::query()
            ->with(self::RELATIONS)
            ->withAllocatedCount()
            ->find($id);
    }

    public function create(NodeData $data): Node
    {
        $this->assertNodeGroupExists($data->nodeGroupId);
        $this->assertGroupIdsExist($data->resolvedGroupIds());
        $this->assertModuleRegistered($data->module);

        return DB::transaction(function () use ($data): Node {
            $attributes = $data->toAttributes();

            if ($data->credentialsProvided) {
                $attributes['credentials'] = $this->credentials->buildForCreate($data->credentials);
            }

            $node = Node::query()->create($attributes);
            $this->syncGroupRelations($node, $data);

            return $node->fresh(self::RELATIONS) ?? $node;
        });
    }

    public function update(Node $node, NodeData $data): Node
    {
        $this->assertNodeGroupExists($data->nodeGroupId);
        $this->assertGroupIdsExist($data->resolvedGroupIds());
        $this->assertModuleRegistered($data->module);

        return DB::transaction(function () use ($node, $data): Node {
            $attributes = $data->toAttributes();

            if ($data->credentialsProvided) {
                $attributes['credentials'] = $this->credentials->mergeForUpdate($node, $data->credentials);
            }

            $node->update($attributes);

            if ($data->shouldSyncGroupRelations()) {
                $this->syncGroupRelations($node->fresh() ?? $node, $data);
            }

            return $node->fresh(self::RELATIONS) ?? $node;
        });
    }

    public function delete(Node $node): void
    {
        if ($node->allocatedServicesCount() > 0) {
            throw new InvalidArgumentException('Cannot delete a node with allocated services.');
        }

        DB::transaction(function () use ($node): void {
            $node->groupRelations()->delete();
            $node->delete();
        });
    }

    private function syncGroupRelations(Node $node, NodeData $data): void
    {
        $groupIds = $data->resolvedGroupIds();
        $primaryGroupId = $data->nodeGroupId ?? ($groupIds[0] ?? null);

        NodeGroupRelation::query()
            ->where('node_id', $node->id)
            ->when($groupIds !== [], fn ($query) => $query->whereNotIn('node_group_id', $groupIds))
            ->delete();

        foreach ($groupIds as $index => $groupId) {
            NodeGroupRelation::query()->updateOrCreate(
                [
                    'node_id' => $node->id,
                    'node_group_id' => $groupId,
                ],
                [
                    'is_primary' => $primaryGroupId !== null && $groupId === $primaryGroupId,
                    'sort_order' => $index,
                ],
            );
        }
    }

    private function assertNodeGroupExists(?int $groupId): void
    {
        if ($groupId === null) {
            return;
        }

        if (! NodeGroup::query()->whereKey($groupId)->exists()) {
            throw new InvalidArgumentException("Node group [{$groupId}] does not exist.");
        }
    }

    /**
     * @param  list<int>  $groupIds
     */
    private function assertGroupIdsExist(array $groupIds): void
    {
        if ($groupIds === []) {
            return;
        }

        $existing = NodeGroup::query()->whereIn('id', $groupIds)->count();

        if ($existing !== count($groupIds)) {
            throw new InvalidArgumentException('One or more node groups do not exist.');
        }
    }

    private function assertModuleRegistered(?string $module): void
    {
        if ($module === null || $module === '') {
            return;
        }

        if ($this->providers->hasNode($module)) {
            return;
        }

        throw new InvalidArgumentException("Unknown provider module [{$module}].");
    }
}
