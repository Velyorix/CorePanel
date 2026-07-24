<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Services\ApiClientAccessService;
use Core\API\Support\ApiPaginationMeta;
use Core\API\Support\ApiResponse;
use Core\Auth\Models\User;
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

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ids = $this->access->accessibleClientIds($user);

        $paginator = Service::query()
            ->with('product')
            ->whereIn('client_id', $ids === [] ? [0] : $ids)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (Service $service): array => ApiResourcePresenter::service($service))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator),
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
}
