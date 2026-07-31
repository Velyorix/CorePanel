<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexInvoiceRequest;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Services\ApiClientAccessService;
use Core\API\Support\ApiPaginationMeta;
use Core\API\Support\ApiResourceListQuery;
use Core\API\Support\ApiResponse;
use Core\Auth\Models\User;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Exceptions\InvalidPaymentException;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\PaymentService;
use Core\Clients\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly ApiClientAccessService $access,
        private readonly PaymentService $payments,
    ) {
    }

    public function index(IndexInvoiceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->filters();
        $ids = $this->resolveAccessibleClientIds($user, $filters['client_id'] ?? null);

        $query = Invoice::query()
            ->whereIn('client_id', $ids === [] ? [0] : $ids)
            ->where('status', '!=', InvoiceStatus::Draft);

        ApiResourceListQuery::applyInvoices($query, [
            ...$filters,
            'client_id' => null,
        ]);

        $paginator = $query->paginate($request->perPage());

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (Invoice $invoice): array => ApiResourcePresenter::invoice($invoice))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator, $filters),
        );
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->access->assertCanAccessOwnedResource($user, $invoice);

        if ($invoice->status === InvoiceStatus::Draft) {
            abort(404, __('Resource not found.'));
        }

        $invoice->loadMissing('items');

        return ApiResponse::success(ApiResourcePresenter::invoice($invoice), $request);
    }

    public function pay(Request $request, Invoice $invoice): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->access->assertCanAccessOwnedResource($user, $invoice);

        if ($invoice->status === InvoiceStatus::Draft) {
            abort(404, __('Resource not found.'));
        }

        try {
            $result = $this->payments->collect(
                $invoice,
                ManualTransferGateway::KEY,
                actor: $user,
            );
        } catch (InvalidPaymentException $exception) {
            return ApiResponse::error('payment_failed', $exception->getMessage(), 422);
        }

        $invoice = $invoice->fresh(['items']) ?? $invoice;

        return ApiResponse::success([
            'invoice' => ApiResourcePresenter::invoice($invoice),
            'paid_with_credit_only' => $result->paidWithCreditOnly(),
        ], $request);
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
