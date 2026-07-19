<?php

namespace Core\Billing\Services;

use Core\Auth\Models\User;
use Core\Billing\Enums\ClientCreditTransactionType;
use Core\Billing\Enums\CreditNoteSettlement;
use Core\Billing\Enums\CreditNoteStatus;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Exceptions\InvalidCreditNoteException;
use Core\Billing\Models\CreditNote;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Credit note lifecycle: draft from invoice, issue to wallet or payment refund.
 */
class CreditNoteService
{
    public function __construct(
        private readonly CreditNoteNumberService $numbers,
        private readonly ClientCreditService $credits,
        private readonly PaymentService $payments,
    ) {
    }

    public function createFromInvoice(
        Invoice $invoice,
        string $amount,
        ?string $reason = null,
        ?string $notes = null,
        ?User $createdBy = null,
    ): CreditNote {
        $money = $this->assertPositiveMoney($amount);
        $this->assertInvoiceEligible($invoice);

        return DB::transaction(function () use ($invoice, $money, $reason, $notes, $createdBy): CreditNote {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertInvoiceEligible($lockedInvoice);
            $this->assertAmountWithinRemaining($lockedInvoice, $money);

            return CreditNote::query()->create([
                'credit_note_number' => null,
                'invoice_id' => $lockedInvoice->id,
                'client_id' => $lockedInvoice->client_id,
                'created_by' => $createdBy?->id,
                'currency' => $lockedInvoice->currency ?? 'EUR',
                'amount' => $money,
                'status' => CreditNoteStatus::Draft,
                'settlement' => null,
                'payment_id' => null,
                'reason' => $reason,
                'notes' => $notes,
                'issued_at' => null,
            ]);
        });
    }

    public function issueToWallet(CreditNote $creditNote, ?User $createdBy = null): CreditNote
    {
        return $this->issue($creditNote, CreditNoteSettlement::Wallet, null, $createdBy);
    }

    public function issueWithPaymentRefund(
        CreditNote $creditNote,
        Payment $payment,
        ?User $createdBy = null,
    ): CreditNote {
        return $this->issue($creditNote, CreditNoteSettlement::PaymentRefund, $payment, $createdBy);
    }

    public function issue(
        CreditNote $creditNote,
        CreditNoteSettlement $settlement,
        ?Payment $payment = null,
        ?User $createdBy = null,
    ): CreditNote {
        return DB::transaction(function () use ($creditNote, $settlement, $payment, $createdBy): CreditNote {
            $locked = CreditNote::query()
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CreditNoteStatus::Draft) {
                throw new InvalidCreditNoteException('Only draft credit notes can be issued.');
            }

            $invoice = Invoice::query()
                ->whereKey($locked->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertInvoiceEligible($invoice);
            $this->assertAmountWithinRemaining($invoice, (string) $locked->amount, excludeCreditNoteId: $locked->id);

            $numbered = $this->numbers->assignNumber($locked);

            if ($settlement === CreditNoteSettlement::Wallet) {
                $this->settleToWallet($numbered, $invoice, $createdBy);
            } else {
                $this->settlePaymentRefund($numbered, $invoice, $payment);
            }

            $numbered->forceFill([
                'status' => CreditNoteStatus::Issued,
                'settlement' => $settlement,
                'payment_id' => $settlement === CreditNoteSettlement::PaymentRefund
                    ? $payment?->id
                    : null,
                'issued_at' => now(),
                'created_by' => $createdBy?->id ?? $numbered->created_by,
            ])->save();

            $this->markInvoiceRefundedIfFullyCredited($invoice);

            return $numbered->fresh(['invoice', 'client', 'payment']) ?? $numbered;
        });
    }

    public function cancel(CreditNote $creditNote): CreditNote
    {
        return DB::transaction(function () use ($creditNote): CreditNote {
            $locked = CreditNote::query()
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CreditNoteStatus::Draft) {
                throw new InvalidCreditNoteException('Only draft credit notes can be cancelled.');
            }

            $locked->forceFill([
                'status' => CreditNoteStatus::Cancelled,
            ])->save();

            return $locked->fresh(['invoice', 'client']) ?? $locked;
        });
    }

    /**
     * @return Collection<int, CreditNote>
     */
    public function forInvoice(Invoice $invoice): Collection
    {
        return CreditNote::query()
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->get();
    }

    public function amountIssuedForInvoice(Invoice $invoice): string
    {
        $sum = (float) CreditNote::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', CreditNoteStatus::Issued)
            ->sum('amount');

        return $this->money($sum);
    }

    public function amountReservedForInvoice(Invoice $invoice, ?int $excludeCreditNoteId = null): string
    {
        $query = CreditNote::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', [CreditNoteStatus::Draft, CreditNoteStatus::Issued]);

        if ($excludeCreditNoteId !== null) {
            $query->whereKeyNot($excludeCreditNoteId);
        }

        return $this->money((float) $query->sum('amount'));
    }

    private function settleToWallet(CreditNote $creditNote, Invoice $invoice, ?User $createdBy): void
    {
        $client = $invoice->client;

        if ($client === null) {
            throw new InvalidCreditNoteException('Invoice has no client for wallet credit.');
        }

        $this->credits->add(
            $client,
            (string) $creditNote->amount,
            ClientCreditTransactionType::Refund,
            description: $creditNote->reason ?? __('Credit note :number', [
                'number' => $creditNote->credit_note_number ?? $creditNote->id,
            ]),
            reference: 'credit_note:'.$creditNote->id,
            idempotencyKey: 'credit_note:'.$creditNote->id.':wallet',
            createdBy: $createdBy,
            currency: $creditNote->currency ?? 'EUR',
        );
    }

    private function settlePaymentRefund(CreditNote $creditNote, Invoice $invoice, ?Payment $payment): void
    {
        if ($payment === null) {
            throw new InvalidCreditNoteException('A payment is required for payment refund settlement.');
        }

        if ((int) $payment->invoice_id !== (int) $invoice->id) {
            throw new InvalidCreditNoteException('Payment does not belong to this invoice.');
        }

        if ($payment->status !== PaymentStatus::Completed) {
            throw new InvalidCreditNoteException('Only completed payments can be refunded via a credit note.');
        }

        if ((float) $creditNote->amount > (float) $payment->amount + 0.00001) {
            throw new InvalidCreditNoteException('Credit note amount cannot exceed the payment amount.');
        }

        $this->payments->refund(
            $payment,
            (string) $creditNote->amount,
            $creditNote->reason,
        );
    }

    private function markInvoiceRefundedIfFullyCredited(Invoice $invoice): void
    {
        $issued = (float) $this->amountIssuedForInvoice($invoice);
        // Include the note being issued in the same transaction after status flip —
        // caller saves Issued before this; amountIssuedForInvoice will see it.
        $total = (float) $invoice->total_amount;

        if ($total <= 0 || $issued + 0.00001 < $total) {
            return;
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Refunded,
            'paid_at' => null,
        ])->save();
    }

    private function assertInvoiceEligible(Invoice $invoice): void
    {
        if (in_array($invoice->status, [
            InvoiceStatus::Draft,
            InvoiceStatus::Cancelled,
            InvoiceStatus::Refunded,
        ], true)) {
            throw new InvalidCreditNoteException(
                'Credit notes can only be created for open or paid invoices.',
            );
        }

        if ((float) $invoice->total_amount <= 0) {
            throw new InvalidCreditNoteException('Invoice total must be greater than zero.');
        }
    }

    private function assertAmountWithinRemaining(
        Invoice $invoice,
        string $amount,
        ?int $excludeCreditNoteId = null,
    ): void {
        $reserved = (float) $this->amountReservedForInvoice($invoice, $excludeCreditNoteId);
        $remaining = (float) $invoice->total_amount - $reserved;

        if ((float) $amount > $remaining + 0.00001) {
            throw new InvalidCreditNoteException(
                'Credit note amount exceeds the remaining creditable balance on this invoice.',
            );
        }
    }

    private function assertPositiveMoney(string $amount): string
    {
        if (! is_numeric($amount)) {
            throw new InvalidCreditNoteException('Credit note amount must be numeric.');
        }

        $money = $this->money((float) $amount);

        if ((float) $money <= 0) {
            throw new InvalidCreditNoteException('Credit note amount must be greater than zero.');
        }

        return $money;
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
