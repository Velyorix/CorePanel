<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Support\ApiPaginationMeta;
use Core\API\Support\ApiResponse;
use Core\Auth\Models\User;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NodeController extends Controller
{
    public function __construct(
        private readonly NodeService $nodes,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Node::class);

        $paginator = $this->nodes->paginateForAdmin([], 20);

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (Node $node): array => ApiResourcePresenter::node($node))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator),
        );
    }

    public function show(Request $request, Node $node): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $node);

        $node = $this->nodes->find($node->id) ?? $node;

        return ApiResponse::success(ApiResourcePresenter::node($node), $request);
    }
}
