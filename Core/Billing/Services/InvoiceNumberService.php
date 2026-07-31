<?php

namespace Core\Billing\Services;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\BillingSequence;
use Core\Billing\Models\Invoice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Configurable invoice numbering.
 * Assigns numbers only — does not change invoice status.
 */
class InvoiceNumberService
{
    public const SEQUENCE_NAME = 'invoice';

    public function __construct(
        private readonly BillingSettings $billingSettings,
    ) {
    }

    /**
     * Assign the next configured invoice number.
     * Idempotent when invoice_number is already set.
     */
    public function assignNumber(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (filled($locked->invoice_number)) {
                return $locked;
            }

            if ($locked->status !== InvoiceStatus::Draft) {
                throw new InvalidArgumentException(
                    'Only draft invoices can receive an invoice number.',
                );
            }

            $number = $this->nextFormattedNumber();

            $locked->forceFill([
                'invoice_number' => $number,
            ])->save();

            return $locked->fresh(['items', 'client', 'order']) ?? $locked;
        });
    }

    /**
     * Preview the next number without consuming a sequence value.
     */
    public function previewNext(): string
    {
        $sequence = BillingSequence::query()
            ->where('name', self::SEQUENCE_NAME)
            ->first();

        $year = (int) now()->format('Y');
        $nextValue = 1;

        if ($sequence !== null) {
            $nextValue = $this->shouldReset($sequence, $year)
                ? 1
                : ((int) $sequence->current_value) + 1;
        }

        return $this->format($nextValue, $year);
    }

    private function nextFormattedNumber(): string
    {
        $sequence = BillingSequence::query()
            ->where('name', self::SEQUENCE_NAME)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            $sequence = BillingSequence::query()->create([
                'name' => self::SEQUENCE_NAME,
                'current_value' => 0,
                'year' => (int) now()->format('Y'),
            ]);

            $sequence = BillingSequence::query()
                ->whereKey($sequence->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $year = (int) now()->format('Y');

        if ($this->shouldReset($sequence, $year)) {
            $sequence->forceFill([
                'current_value' => 0,
                'year' => $year,
            ])->save();
        }

        $next = ((int) $sequence->current_value) + 1;

        if ($next < 1) {
            throw new RuntimeException('Invoice sequence produced an invalid value.');
        }

        $sequence->forceFill([
            'current_value' => $next,
            'year' => $year,
        ])->save();

        return $this->format($next, $year);
    }

    private function shouldReset(BillingSequence $sequence, int $year): bool
    {
        if (! (bool) config('corepanel.billing.invoice_numbering.reset_yearly', true)) {
            return false;
        }

        return $sequence->year === null || (int) $sequence->year !== $year;
    }

    private function format(int $value, int $year): string
    {
        $prefix = $this->billingSettings->invoicePrefix();
        $separator = (string) config('corepanel.billing.invoice_numbering.separator', '-');
        $padding = max(1, (int) config('corepanel.billing.invoice_numbering.padding', 6));
        $includeYear = (bool) config('corepanel.billing.invoice_numbering.include_year', true);

        $parts = [$prefix];

        if ($includeYear) {
            $parts[] = (string) $year;
        }

        $parts[] = str_pad((string) $value, $padding, '0', STR_PAD_LEFT);

        return implode($separator, $parts);
    }
}
