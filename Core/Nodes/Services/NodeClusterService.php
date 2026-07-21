<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeClusterData;
use Core\Nodes\Enums\NodeClusterStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeCluster;
use Core\Nodes\Models\NodeClusterMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NodeClusterService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     status?: NodeClusterStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'sort_order';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'key', 'status', 'location', 'sort_order', 'created_at'], true)) {
            $sort = 'sort_order';
        }

        $query = NodeCluster::query()->withCount('nodes');

        if ($status instanceof NodeClusterStatus) {
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

    public function create(NodeClusterData $data): NodeCluster
    {
        $this->assertKeyIsUnique($data->key);

        return DB::transaction(function () use ($data): NodeCluster {
            $cluster = NodeCluster::query()->create($data->toAttributes());

            if ($data->nodeIdsProvided) {
                $this->syncMembers($cluster, $data->nodeIds ?? []);
            }

            return $cluster->fresh(['nodes']) ?? $cluster;
        });
    }

    public function update(NodeCluster $cluster, NodeClusterData $data): NodeCluster
    {
        $this->assertKeyIsUnique($data->key, $cluster->id);

        return DB::transaction(function () use ($cluster, $data): NodeCluster {
            $cluster->update($data->toAttributes());

            if ($data->nodeIdsProvided) {
                $this->syncMembers($cluster->fresh() ?? $cluster, $data->nodeIds ?? []);
            }

            return $cluster->fresh(['nodes']) ?? $cluster;
        });
    }

    public function delete(NodeCluster $cluster): void
    {
        DB::transaction(function () use ($cluster): void {
            $cluster->members()->delete();
            $cluster->delete();
        });
    }

    public function findByKey(string $key): ?NodeCluster
    {
        return NodeCluster::query()->where('key', $key)->first();
    }

    /**
     * @param  list<int>  $nodeIds
     */
    public function syncMembers(NodeCluster $cluster, array $nodeIds): void
    {
        $nodeIds = array_values(array_unique(array_map(intval(...), $nodeIds)));

        if ($nodeIds !== []) {
            $existing = Node::query()->whereIn('id', $nodeIds)->count();

            if ($existing !== count($nodeIds)) {
                throw new InvalidArgumentException('One or more selected servers do not exist.');
            }
        }

        NodeClusterMember::query()
            ->where('node_cluster_id', $cluster->id)
            ->when($nodeIds !== [], fn ($query) => $query->whereNotIn('node_id', $nodeIds))
            ->delete();

        foreach ($nodeIds as $index => $nodeId) {
            NodeClusterMember::query()->updateOrCreate(
                [
                    'node_id' => $nodeId,
                    'node_cluster_id' => $cluster->id,
                ],
                [
                    'sort_order' => $index,
                    'weight' => 100,
                ],
            );
        }
    }

    private function assertKeyIsUnique(string $key, ?int $ignoreId = null): void
    {
        $query = NodeCluster::query()->where('key', $key);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('A node cluster with this key already exists.');
        }
    }
}
