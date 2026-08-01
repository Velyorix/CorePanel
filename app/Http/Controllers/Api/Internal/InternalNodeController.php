<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Support\ApiResponse;
use Core\Nodes\Models\Node;
use Core\Sync\Services\NodeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalNodeController extends Controller
{
    public function __construct(
        private readonly NodeSyncService $sync,
    ) {
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node_id' => ['required', 'integer', 'min:1', 'exists:nodes,id'],
        ]);

        $node = Node::query()->findOrFail((int) $validated['node_id']);
        $outcome = $this->sync->syncNode($node);
        $fresh = $node->fresh() ?? $node;

        return ApiResponse::success([
            'outcome' => $outcome,
            'node' => ApiResourcePresenter::node($fresh),
        ], $request);
    }
}
