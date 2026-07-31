<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexServiceRequest;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Services\ApiClientAccessService;
use Core\API\Support\ApiPaginationMeta;
use Core\API\Support\ApiResourceListQuery;
use Core\API\Support\ApiResponse;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ApiClientAccessService $access,
        private readonly ServiceControlService $controls,
    ) {
    }

    public function index(IndexServiceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->filters();
        $ids = $this->resolveAccessibleClientIds($user, $filters['client_id'] ?? null);

        $query = Service::query()
            ->with('product')
            ->whereIn('client_id', $ids === [] ? [0] : $ids);

        ApiResourceListQuery::applyServices($query, [
            ...$filters,
            'client_id' => null,
        ]);

        $paginator = $query->paginate($request->perPage());

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (Service $service): array => ApiResourcePresenter::service($service))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator, $filters),
        );
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->access->assertCanAccessOwnedResource($user, $service);

        $service->loadMissing('product');

        return ApiResponse::success(ApiResourcePresenter::service($service), $request);
    }

    public function restart(Request $request, Service $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->access->assertCanAccessOwnedResource($user, $service);

        try {
            $service = $this->controls->restart($service, $user);
        } catch (Throwable $exception) {
            return ApiResponse::error('service_action_failed', $exception->getMessage(), 422);
        }

        $service->loadMissing('product');

        return ApiResponse::success(ApiResourcePresenter::service($service), $request);
    }

    public function suspend(Request $request, Service $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->access->assertCanAccessOwnedResource($user, $service);

        try {
            $service = $this->controls->suspend($service, $user);
        } catch (Throwable $exception) {
            return ApiResponse::error('service_action_failed', $exception->getMessage(), 422);
        }

        $service->loadMissing('product');

        return ApiResponse::success(ApiResourcePresenter::service($service), $request);
    }

    /**
     * @return list<int>
     */
    private function resolveAccessibleClientIds(User $user, ?int $clientId): array
    {
        $ids = $this->access->accessibleClientIds($user);

        if ($clientId === null) {
            return $ids;
        }

        $client = Client::query()->find($clientId);
        if ($client === null) {
            abort(404, __('Resource not found.'));
        }

        $this->access->assertCanAccessClient($user, $client);

        return [$clientId];
    }
}
