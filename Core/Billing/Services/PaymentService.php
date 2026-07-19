<?php

namespace Core\Billing\Services;

use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Exceptions\InvalidPaymentException;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Payment;
use Core\Clients\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Persists payments and delegates provider operations to registered gateways.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
    ) {
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: PaymentStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'amount',
            'status',
            'method',
            'paid_at',
            'created_at',
        ], true)) {
            $sort = 'created_at';
        }

        $query = Payment::query()->with(['invoice', 'client']);

        if ($status instanceof PaymentStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('transaction_id', 'like', $term)
                    ->orWhere('gateway_reference', 'like', $term)
                    ->orWhere('method', 'like', $term)
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($term): void {
                        $invoiceQuery->where('invoice_number', 'like', $term);
                    })
                    ->orWhereHas('client', function ($clientQuery) use ($term): void {
                        $clientQuery->where('company_name', 'like', $term);
                    });
            });
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{
     *     status?: PaymentStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForClient(Client $client, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['amount', 'status', 'method', 'paid_at', 'created_at'], true)) {
            $sort = 'created_at';
        }

        $query = Payment::query()
            ->where('client_id', $client->id)
            ->with(['invoice']);

        if ($status instanceof PaymentStatus) {
            $query->where('status', $status->value);
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Start a payment: create a pending row and delegate to the gateway.
     */
    public function initiate(
        Invoice $invoice,
        string $method,
        ?string $amount = null,
        ?PaymentContext $context = null,
        ?string $notes = null,
    ): Payment {
        if (! $invoice->isPayable()) {
            throw new InvalidPaymentException(
                'Only unpaid or overdue invoices with a remaining balance can accept payments.',
            );
        }

        $due = $invoice->amountDue();
        $resolvedAmount = $this->money((float) ($amount ?? $due));

        if ((float) $resolvedAmount <= 0) {
            throw new InvalidPaymentException('Payment amount must be greater than zero.');
        }

        if ((float) $resolvedAmount > (float) $due + 0.00001) {
            throw new InvalidPaymentException('Payment amount cannot exceed the invoice balance due.');
        }

        $gateway = $this->gateways->get($method);
        $context ??= new PaymentContext;

        return DB::transaction(function () use ($invoice, $method, $resolvedAmount, $context, $notes, $gateway): Payment {
            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'method' => $method,
                'currency' => $invoice->currency ?? 'EUR',
                'amount' => $resolvedAmount,
                'status' => PaymentStatus::Pending,
                'transaction_id' => null,
                'gateway_reference' => null,
                'notes' => $notes,
                'paid_at' => null,
            ]);

            $result = $gateway->createPayment($payment, $context);

            return $this->applyGatewayResult($payment->fresh() ?? $payment, $result);
        });
    }

    /**
     * Mark a payment completed and refresh invoice paid status.
     */
    public function complete(
        Payment $payment,
        ?string $transactionId = null,
        ?string $gatewayReference = null,
    ): Payment {
        if ($payment->status === PaymentStatus::Completed) {
            return $payment->fresh(['invoice', 'client']) ?? $payment;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            throw new InvalidPaymentException(
                'Only pending payments can be marked completed.',
            );
        }

        return DB::transaction(function () use ($payment, $transactionId, $gatewayReference): Payment {
            $payment->forceFill([
                'status' => PaymentStatus::Completed,
                'transaction_id' => $transactionId ?? $payment->transaction_id,
                'gateway_reference' => $gatewayReference ?? $payment->gateway_reference,
                'paid_at' => $payment->paid_at ?? now(),
            ])->save();

            $invoice = $payment->invoice()->first();

            if ($invoice !== null) {
                $this->applyToInvoice($invoice);
            }

            return $payment->fresh(['invoice', 'client']) ?? $payment;
        });
    }

    /**
     * Mark a pending payment as failed without changing the invoice.
     */
    public function fail(Payment $payment, ?string $notes = null): Payment
    {
        if ($payment->status === PaymentStatus::Failed) {
            return $payment->fresh(['invoice', 'client']) ?? $payment;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            throw new InvalidPaymentException('Only pending payments can be marked failed.');
        }

        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'notes' => $notes ?? $payment->notes,
            'paid_at' => null,
        ])->save();

        return $payment->fresh(['invoice', 'client']) ?? $payment;
    }

    /**
     * Ask the gateway to verify payment status and apply the result.
     */
    public function verify(Payment $payment): Payment
    {
        $gateway = $this->gateways->get($payment->method);
        $result = $gateway->verifyPayment($payment);

        return $this->applyGatewayResult($payment, $result);
    }

    /**
     * Refund a completed payment via the gateway.
     */
    public function refund(Payment $payment, ?string $amount = null, ?string $notes = null): Payment
    {
        if ($payment->status !== PaymentStatus::Completed) {
            throw new InvalidPaymentException('Only completed payments can be refunded.');
        }

        $refundAmount = $this->money((float) ($amount ?? $payment->amount));

        if ((float) $refundAmount <= 0) {
            throw new InvalidPaymentException('Refund amount must be greater than zero.');
        }

        if ((float) $refundAmount > (float) $payment->amount + 0.00001) {
            throw new InvalidPaymentException('Refund amount cannot exceed the payment amount.');
        }

        $gateway = $this->gateways->get($payment->method);

        return DB::transaction(function () use ($payment, $refundAmount, $notes, $gateway): Payment {
            $result = $gateway->refundPayment($payment, $refundAmount);

            if ($result->status !== PaymentStatus::Refunded) {
                throw new InvalidPaymentException(
                    $result->message ?? 'Gateway did not confirm the refund.',
                );
            }

            $payment->forceFill([
                'status' => PaymentStatus::Refunded,
                'transaction_id' => $result->transactionId ?? $payment->transaction_id,
                'gateway_reference' => $result->gatewayReference ?? $payment->gateway_reference,
                'notes' => $notes ?? $result->message ?? $payment->notes,
                'paid_at' => $payment->paid_at,
            ])->save();

            $invoice = $payment->invoice()->first();

            if ($invoice !== null) {
                $this->applyToInvoice($invoice);
            }

            return $payment->fresh(['invoice', 'client']) ?? $payment;
        });
    }

    public function amountDue(Invoice $invoice): string
    {
        return $invoice->amountDue();
    }

    /**
     * @return Collection<int, Payment>
     */
    public function forInvoice(Invoice $invoice): Collection
    {
        return Payment::query()
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->get();
    }

    private function applyGatewayResult(Payment $payment, PaymentGatewayResult $result): Payment
    {
        return match ($result->status) {
            PaymentStatus::Completed => $this->complete(
                $payment,
                $result->transactionId,
                $result->gatewayReference,
            ),
            PaymentStatus::Failed => $this->fail($payment, $result->message),
            PaymentStatus::Pending => $this->updatePendingRefs($payment, $result),
            PaymentStatus::Refunded => throw new InvalidPaymentException(
                'Gateway returned refunded status outside of refund().',
            ),
            PaymentStatus::Chargeback => throw new InvalidPaymentException(
                'Chargeback status is not handled by PaymentService yet.',
            ),
        };
    }

    private function updatePendingRefs(Payment $payment, PaymentGatewayResult $result): Payment
    {
        $payment->forceFill([
            'status' => PaymentStatus::Pending,
            'transaction_id' => $result->transactionId ?? $payment->transaction_id,
            'gateway_reference' => $result->gatewayReference ?? $payment->gateway_reference,
            'notes' => $result->message ?? $payment->notes,
        ])->save();

        return $payment->fresh(['invoice', 'client']) ?? $payment;
    }

    /**
     * Sync invoice paid / unpaid status from completed payment totals.
     */
    private function applyToInvoice(Invoice $invoice): void
    {
        if (in_array($invoice->status, [
            InvoiceStatus::Draft,
            InvoiceStatus::Cancelled,
            InvoiceStatus::Refunded,
        ], true)) {
            return;
        }

        $paid = $invoice->amountPaid();
        $total = $this->money((float) $invoice->total_amount);

        if ((float) $paid + 0.00001 >= (float) $total && (float) $total > 0) {
            $invoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_at' => $invoice->paid_at ?? now(),
            ])->save();

            return;
        }

        if ($invoice->status === InvoiceStatus::Paid) {
            $invoice->forceFill([
                'status' => InvoiceStatus::Unpaid,
                'paid_at' => null,
            ])->save();
        }
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
