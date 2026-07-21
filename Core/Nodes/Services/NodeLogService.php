<?php

namespace Core\Nodes\Services;

use Core\Nodes\Enums\NodeLogStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeLog;
use Illuminate\Support\Collection;

class NodeLogService
{
    public function record(
        Node $node,
        string $action,
        NodeLogStatus $status,
        ?int $performedBy = null,
        ?array $response = null,
    ): NodeLog {
        $log = new NodeLog([
            'node_id' => $node->id,
            'action' => $action,
            'status' => $status,
            'response' => $response,
            'performed_by' => $performedBy,
            'created_at' => now(),
        ]);

        $log->save();

        return $log;
    }

    /**
     * @return Collection<int, NodeLog>
     */
    public function forNode(Node $node, ?int $limit = null): Collection
    {
        $query = NodeLog::query()
            ->with('performer')
            ->where('node_id', $node->id)
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        return $query->get();
    }
}
