<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexNodeRequest;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Support\ApiPaginationMeta;
use Core\API\Support\ApiResourceListQuery;
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

    public function index(IndexNodeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Node::class);

        $filters = $request->filters();
        $paginator = $this->nodes->paginateForAdmin(
            ApiResourceListQuery::nodeServiceFilters($filters),
            $request->perPage(),
        );

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (Node $node): array => ApiResourcePresenter::node($node))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator, $filters),
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
