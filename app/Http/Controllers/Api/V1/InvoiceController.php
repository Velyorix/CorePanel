<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Services\ApiClientAccessService;
use Core\API\Support\ApiPaginationMeta;
use Core\API\Support\ApiResponse;
use Core\Auth\Models\User;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Exceptions\InvalidPaymentException;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly ApiClientAccessService $access,
        private readonly PaymentService $payments,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ids = $this->access->accessibleClientIds($user);

        $paginator = Invoice::query()
            ->whereIn('client_id', $ids === [] ? [0] : $ids)
            ->where('status', '!=', InvoiceStatus::Draft)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (Invoice $invoice): array => ApiResourcePresenter::invoice($invoice))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator),
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
}
