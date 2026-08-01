<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Support\ApiResponse;
use Core\Orders\Models\Order;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceControlService;
use Core\Services\Services\ServiceCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class InternalServiceController extends Controller
{
    public function __construct(
        private readonly ServiceCreationService $creation,
        private readonly ServiceControlService $controls,
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'min:1', 'exists:orders,id'],
        ]);

        $order = Order::query()->with('items.product')->findOrFail((int) $validated['order_id']);

        try {
            $created = $this->creation->createFromPaidOrder($order);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('service_create_failed', $exception->getMessage(), 422);
        }

        return ApiResponse::success([
            'created' => $created
                ->map(static fn (Service $service): array => ApiResourcePresenter::service(
                    $service->loadMissing('product'),
                ))
                ->values()
                ->all(),
            'count' => $created->count(),
        ], $request, status: 201);
    }

    public function suspend(Request $request): JsonResponse
    {
        $service = $this->resolveService($request);

        try {
            $service = $this->controls->suspend($service);
        } catch (Throwable $exception) {
            return ApiResponse::error('service_action_failed', $exception->getMessage(), 422);
        }

        $service->loadMissing('product');

        return ApiResponse::success(ApiResourcePresenter::service($service), $request);
    }

    public function terminate(Request $request): JsonResponse
    {
        $service = $this->resolveService($request);

        try {
            $service = $this->controls->terminate($service);
        } catch (Throwable $exception) {
            return ApiResponse::error('service_action_failed', $exception->getMessage(), 422);
        }

        $service->loadMissing('product');

        return ApiResponse::success(ApiResourcePresenter::service($service), $request);
    }

    private function resolveService(Request $request): Service
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'min:1', 'exists:services,id'],
        ]);

        return Service::query()
            ->with(['client', 'product'])
            ->findOrFail((int) $validated['service_id']);
    }
}
