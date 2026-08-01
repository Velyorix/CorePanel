<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWebhookRequest;
use App\Http\Requests\Api\V1\UpdateWebhookRequest;
use Core\API\Http\Presenters\V1\WebhookPresenter;
use Core\API\Support\ApiPagination;
use Core\API\Support\ApiPaginationMeta;
use Core\API\Support\ApiResponse;
use Core\Auth\Models\User;
use Core\Webhooks\Enums\WebhookEvent;
use Core\Webhooks\Models\Webhook;
use Core\Webhooks\Models\WebhookDelivery;
use Core\Webhooks\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhooks,
    ) {
    }

    public function events(): JsonResponse
    {
        return ApiResponse::success([
            'events' => WebhookEvent::values(),
        ], request());
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $paginator = $this->webhooks->paginateForUser($user, ApiPagination::perPage($request));

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (Webhook $webhook): array => WebhookPresenter::webhook($webhook))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator),
        );
    }

    public function store(StoreWebhookRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $created = $this->webhooks->create($user, $request->webhookData());

        return ApiResponse::success(
            WebhookPresenter::webhook($created['webhook'], $created['secret']),
            $request,
            status: 201,
        );
    }

    public function show(Request $request, Webhook $webhook): JsonResponse
    {
        $this->assertOwner($request, $webhook);

        return ApiResponse::success(WebhookPresenter::webhook($webhook), $request);
    }

    public function update(UpdateWebhookRequest $request, Webhook $webhook): JsonResponse
    {
        $this->assertOwner($request, $webhook);
        $webhook = $this->webhooks->update($webhook, $request->webhookData());

        return ApiResponse::success(WebhookPresenter::webhook($webhook), $request);
    }

    public function destroy(Request $request, Webhook $webhook): JsonResponse
    {
        $this->assertOwner($request, $webhook);
        $this->webhooks->delete($webhook);

        return ApiResponse::success(['deleted' => true], $request);
    }

    public function rotateSecret(Request $request, Webhook $webhook): JsonResponse
    {
        $this->assertOwner($request, $webhook);
        $rotated = $this->webhooks->rotateSecret($webhook);

        return ApiResponse::success(
            WebhookPresenter::webhook($rotated['webhook'], $rotated['secret']),
            $request,
        );
    }

    public function deliveries(Request $request, Webhook $webhook): JsonResponse
    {
        $this->assertOwner($request, $webhook);

        $paginator = WebhookDelivery::query()
            ->where('webhook_id', $webhook->id)
            ->orderByDesc('id')
            ->paginate(ApiPagination::perPage($request));

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (WebhookDelivery $delivery): array => WebhookPresenter::delivery($delivery))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator),
        );
    }

    private function assertOwner(Request $request, Webhook $webhook): void
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $webhook->user_id !== (int) $user->id) {
            abort(404, __('Resource not found.'));
        }
    }
}
