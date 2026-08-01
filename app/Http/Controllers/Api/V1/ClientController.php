<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexClientRequest;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Services\ApiClientAccessService;
use Core\API\Support\ApiPaginationMeta;
use Core\API\Support\ApiResourceListQuery;
use Core\API\Support\ApiResponse;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(
        private readonly ApiClientAccessService $access,
    ) {
    }

    public function index(IndexClientRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->filters();
        $ids = $this->access->accessibleClientIds($user);

        $query = Client::query()->whereIn('id', $ids === [] ? [0] : $ids);
        ApiResourceListQuery::applyClients($query, $filters);

        $paginator = $query->paginate($request->perPage());

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (Client $client): array => ApiResourcePresenter::client($client))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator, $filters),
        );
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->access->assertCanAccessClient($user, $client);

        return ApiResponse::success(ApiResourcePresenter::client($client), $request);
    }
}
