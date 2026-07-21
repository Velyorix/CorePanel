<?php

namespace Core\Billing\Services;

use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\DataTransferObjects\PaymentGatewayWebhookResult;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Events\InvoicePaid;
use Core\Billing\Events\PaymentCompleted;
use Core\Billing\Events\PaymentFailed;
use Core\Billing\Events\PaymentRefunded;
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
        private readonly GatewayManager $gateways,
        private readonly BillingAuditLogger $auditLogger,
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

        $gateway = $this->gateways->resolve($method);
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
            $before = $this->auditLogger->paymentSnapshot($payment);

            $payment->forceFill([
                'status' => PaymentStatus::Completed,
                'transaction_id' => $transactionId ?? $payment->transaction_id,
                'gateway_reference' => $gatewayReference ?? $payment->gateway_reference,
                'paid_at' => $payment->paid_at ?? now(),
            ])->save();

            $invoice = $payment->invoice()->first();
            $wasPaid = $invoice?->status === InvoiceStatus::Paid;

            if ($invoice !== null) {
                $this->applyToInvoice($invoice);
            }

            $fresh = $payment->fresh(['invoice', 'client']) ?? $payment;

            $this->auditLogger->log(
                BillingAuditLogger::ACTION_PAYMENT_COMPLETED,
                Payment::class,
                $fresh->id,
                $before,
                $this->auditLogger->paymentSnapshot($fresh),
            );

            event(new PaymentCompleted($fresh));

            $refreshedInvoice = $invoice?->fresh();

            if ($refreshedInvoice !== null && $refreshedInvoice->status === InvoiceStatus::Paid && ! $wasPaid) {
                $this->auditLogger->log(
                    BillingAuditLogger::ACTION_INVOICE_PAID,
                    Invoice::class,
                    $refreshedInvoice->id,
                    null,
                    $this->auditLogger->invoiceSnapshot($refreshedInvoice),
                );

                event(new InvoicePaid($refreshedInvoice));
            }

            return $fresh;
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

        $before = $this->auditLogger->paymentSnapshot($payment);

        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'notes' => $notes ?? $payment->notes,
            'paid_at' => null,
        ])->save();

        $fresh = $payment->fresh(['invoice', 'client']) ?? $payment;

        $this->auditLogger->log(
            BillingAuditLogger::ACTION_PAYMENT_FAILED,
            Payment::class,
            $fresh->id,
            $before,
            $this->auditLogger->paymentSnapshot($fresh),
        );

        event(new PaymentFailed($fresh));

        return $fresh;
    }

    /**
     * Ask the gateway to verify payment status and apply the result.
     */
    public function verify(Payment $payment): Payment
    {
        $gateway = $this->gateways->resolve($payment->method, onlyEnabled: false);
        $result = $gateway->verifyPayment($payment);

        return $this->applyGatewayResult($payment, $result);
    }

    /**
     * Dispatch an inbound provider webhook to the registered gateway and apply the result.
     *
     * Signature verification is the gateway's responsibility. Disabled gateways may still
     * receive webhooks so in-flight payments can settle.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function handleWebhook(string $gatewayKey, array $payload, array $headers): ?Payment
    {
        $gateway = $this->gateways->resolve($gatewayKey, onlyEnabled: false);
        $result = $gateway->handleWebhook($payload, $headers);

        if ($result->paymentId === null) {
            return null;
        }

        $payment = Payment::query()->find($result->paymentId);

        if ($payment === null) {
            throw new InvalidPaymentException(
                "Payment [{$result->paymentId}] referenced by webhook was not found.",
            );
        }

        if ($payment->method !== $gatewayKey) {
            throw new InvalidPaymentException(
                'Webhook gateway key does not match the payment method.',
            );
        }

        return $this->applyWebhookResult($payment, $result);
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

        $gateway = $this->gateways->resolve($payment->method, onlyEnabled: false);

        return DB::transaction(function () use ($payment, $refundAmount, $notes, $gateway): Payment {
            $result = $gateway->refundPayment($payment, $refundAmount);

            if ($result->status !== PaymentStatus::Refunded) {
                throw new InvalidPaymentException(
                    $result->message ?? 'Gateway did not confirm the refund.',
                );
            }

            return $this->markRefunded(
                $payment,
                $refundAmount,
                $result->transactionId,
                $result->gatewayReference,
                $notes ?? $result->message,
            );
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
            PaymentStatus::Pending => $this->updatePendingRefs(
                $payment,
                $result->transactionId,
                $result->gatewayReference,
                $result->message,
            ),
            PaymentStatus::Refunded => throw new InvalidPaymentException(
                'Gateway returned refunded status outside of refund().',
            ),
            PaymentStatus::Chargeback => throw new InvalidPaymentException(
                'Chargeback status is not handled by PaymentService yet.',
            ),
        };
    }

    private function applyWebhookResult(Payment $payment, PaymentGatewayWebhookResult $result): Payment
    {
        return match ($result->status) {
            PaymentStatus::Completed => $this->complete(
                $payment,
                $result->transactionId,
                $result->gatewayReference,
            ),
            PaymentStatus::Failed => $this->fail($payment),
            PaymentStatus::Pending => $this->updatePendingRefs(
                $payment,
                $result->transactionId,
                $result->gatewayReference,
            ),
            PaymentStatus::Refunded => $this->markRefunded(
                $payment,
                $this->money((float) $payment->amount),
                $result->transactionId,
                $result->gatewayReference,
            ),
            PaymentStatus::Chargeback => throw new InvalidPaymentException(
                'Chargeback status is not handled by PaymentService yet.',
            ),
        };
    }

    private function markRefunded(
        Payment $payment,
        string $refundAmount,
        ?string $transactionId = null,
        ?string $gatewayReference = null,
        ?string $notes = null,
    ): Payment {
        if ($payment->status === PaymentStatus::Refunded) {
            return $payment->fresh(['invoice', 'client']) ?? $payment;
        }

        if ($payment->status !== PaymentStatus::Completed) {
            throw new InvalidPaymentException('Only completed payments can be refunded.');
        }

        return DB::transaction(function () use ($payment, $refundAmount, $transactionId, $gatewayReference, $notes): Payment {
            $before = $this->auditLogger->paymentSnapshot($payment);

            $payment->forceFill([
                'status' => PaymentStatus::Refunded,
                'transaction_id' => $transactionId ?? $payment->transaction_id,
                'gateway_reference' => $gatewayReference ?? $payment->gateway_reference,
                'notes' => $notes ?? $payment->notes,
                'paid_at' => $payment->paid_at,
            ])->save();

            $invoice = $payment->invoice()->first();

            if ($invoice !== null) {
                $this->applyToInvoice($invoice);
            }

            $fresh = $payment->fresh(['invoice', 'client']) ?? $payment;

            $this->auditLogger->log(
                BillingAuditLogger::ACTION_PAYMENT_REFUNDED,
                Payment::class,
                $fresh->id,
                $before,
                [...$this->auditLogger->paymentSnapshot($fresh), 'refund_amount' => $refundAmount],
            );

            event(new PaymentRefunded($fresh, $refundAmount));

            return $fresh;
        });
    }

    private function updatePendingRefs(
        Payment $payment,
        ?string $transactionId = null,
        ?string $gatewayReference = null,
        ?string $notes = null,
    ): Payment {
        $payment->forceFill([
            'status' => PaymentStatus::Pending,
            'transaction_id' => $transactionId ?? $payment->transaction_id,
            'gateway_reference' => $gatewayReference ?? $payment->gateway_reference,
            'notes' => $notes ?? $payment->notes,
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
