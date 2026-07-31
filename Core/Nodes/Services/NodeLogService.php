<?php

namespace Core\Nodes\Services;

use Core\Nodes\Enums\NodeLogStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeLog;
use Illuminate\Database\Eloquent\Collection;

class NodeLogService
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function record(
        Node $node,
        string $action,
        NodeLogStatus $status,
        ?int $performedBy = null,
        ?array $response = null,
    ): NodeLog {
        return NodeLog::query()->create([
            'node_id' => $node->id,
            'action' => $action,
            'status' => $status,
            'response' => $response,
            'performed_by' => $performedBy,
            'created_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, NodeLog>
     */
    public function forNode(Node $node, int $limit = 20): Collection
    {
        return NodeLog::query()
            ->where('node_id', $node->id)
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }
}
