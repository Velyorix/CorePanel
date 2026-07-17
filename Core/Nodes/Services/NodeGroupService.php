<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeGroupData;
use Core\Nodes\Models\NodeGroup;
use InvalidArgumentException;

class NodeGroupService
{
    public function create(NodeGroupData $data): NodeGroup
    {
        $this->assertKeyIsUnique($data->key);

        return NodeGroup::query()->create($data->toAttributes());
    }

    public function update(NodeGroup $group, NodeGroupData $data): NodeGroup
    {
        $this->assertKeyIsUnique($data->key, $group->id);

        $group->update($data->toAttributes());

        return $group->fresh() ?? $group;
    }

    public function delete(NodeGroup $group): void
    {
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
