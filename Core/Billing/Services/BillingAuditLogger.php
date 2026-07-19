<?php

namespace Core\Billing\Services;

use Core\Billing\Enums\ClientCreditTransactionType;
use Core\Billing\Enums\CreditNoteSettlement;
use Core\Billing\Enums\CreditNoteStatus;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Models\ClientCreditTransaction;
use Core\Billing\Models\CreditNote;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Payment;
use Core\Billing\Models\Quote;
use Core\Support\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class BillingAuditLogger
{
    public const ACTION_INVOICE_ISSUED = 'invoice.issued';

    public const ACTION_INVOICE_PAID = 'invoice.paid';

    public const ACTION_INVOICE_SERVICE_SUSPENDED = 'invoice.service_suspended';

    public const ACTION_PAYMENT_COMPLETED = 'payment.completed';

    public const ACTION_PAYMENT_FAILED = 'payment.failed';

    public const ACTION_PAYMENT_REFUNDED = 'payment.refunded';

    public const ACTION_QUOTE_SENT = 'quote.sent';

    public const ACTION_QUOTE_ACCEPTED = 'quote.accepted';

    public const ACTION_QUOTE_CONVERTED = 'quote.converted';

    public const ACTION_CREDIT_NOTE_ISSUED = 'credit_note.issued';

    public const ACTION_CLIENT_CREDIT_ADDED = 'client_credit.added';

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        ?array $before,
        ?array $after,
        ?int $actorId = null,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $this->auditLogger->record(
            action: $action,
            actorId: $actorId ?? $this->actorId(),
            entityType: $entityType,
            entityId: $entityId,
            before: $before,
            after: $after,
            ipAddress: $this->ipAddress(),
            userAgent: $this->userAgent(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceSnapshot(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status instanceof InvoiceStatus
                ? $invoice->status->value
                : $invoice->status,
            'total_amount' => (string) $invoice->total_amount,
            'amount' => (string) $invoice->total_amount,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentSnapshot(Payment $payment): array
    {
        return [
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'client_id' => $payment->client_id,
            'amount' => (string) $payment->amount,
            'status' => $payment->status instanceof PaymentStatus
                ? $payment->status->value
                : $payment->status,
            'method' => $payment->method,
            'transaction_id' => $payment->transaction_id,
            'gateway_reference' => $payment->gateway_reference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteSnapshot(Quote $quote): array
    {
        return [
            'quote_id' => $quote->id,
            'client_id' => $quote->client_id,
            'quote_number' => $quote->quote_number,
            'status' => $quote->status instanceof QuoteStatus
                ? $quote->status->value
                : $quote->status,
            'total_amount' => (string) $quote->total_amount,
            'amount' => (string) $quote->total_amount,
            'converted_invoice_id' => $quote->converted_invoice_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function creditNoteSnapshot(CreditNote $creditNote): array
    {
        return [
            'credit_note_id' => $creditNote->id,
            'invoice_id' => $creditNote->invoice_id,
            'client_id' => $creditNote->client_id,
            'credit_note_number' => $creditNote->credit_note_number,
            'status' => $creditNote->status instanceof CreditNoteStatus
                ? $creditNote->status->value
                : $creditNote->status,
            'settlement' => $creditNote->settlement instanceof CreditNoteSettlement
                ? $creditNote->settlement->value
                : $creditNote->settlement,
            'amount' => (string) $creditNote->amount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function creditTransactionSnapshot(ClientCreditTransaction $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'client_id' => $transaction->client_id,
            'type' => $transaction->type instanceof ClientCreditTransactionType
                ? $transaction->type->value
                : $transaction->type,
            'amount' => (string) $transaction->amount,
            'balance_after' => (string) $transaction->balance_after,
            'reference' => $transaction->reference,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('corepanel.billing.audit.enabled', true);
    }

    private function actorId(): ?int
    {
        $id = Auth::id();

        return $id !== null ? (int) $id : null;
    }

    private function ipAddress(): ?string
    {
        return Request::ip();
    }

    private function userAgent(): ?string
    {
        $userAgent = Request::userAgent();

        return filled($userAgent) ? (string) $userAgent : null;
    }
}
