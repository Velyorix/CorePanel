<?php

namespace Core\Billing\Services;

use Core\Billing\Enums\CreditNoteStatus;
use Core\Billing\Models\BillingSequence;
use Core\Billing\Models\CreditNote;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Configurable credit note numbering.
 * Assigns numbers only — does not change credit note status.
 */
class CreditNoteNumberService
{
    public const SEQUENCE_NAME = 'credit_note';

    public function __construct(
        private readonly BillingSettings $billingSettings,
    ) {
    }

    /**
     * Assign the next configured credit note number.
     * Idempotent when credit_note_number is already set.
     */
    public function assignNumber(CreditNote $creditNote): CreditNote
    {
        return DB::transaction(function () use ($creditNote): CreditNote {
            $locked = CreditNote::query()
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (filled($locked->credit_note_number)) {
                return $locked;
            }

            if ($locked->status !== CreditNoteStatus::Draft) {
                throw new InvalidArgumentException(
                    'Only draft credit notes can receive a credit note number.',
                );
            }

            $number = $this->nextFormattedNumber();

            $locked->forceFill([
                'credit_note_number' => $number,
            ])->save();

            return $locked->fresh(['invoice', 'client']) ?? $locked;
        });
    }

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
            throw new RuntimeException('Credit note sequence produced an invalid value.');
        }

        $sequence->forceFill([
            'current_value' => $next,
            'year' => $year,
        ])->save();

        return $this->format($next, $year);
    }

    private function shouldReset(BillingSequence $sequence, int $year): bool
    {
        if (! (bool) config('corepanel.billing.credit_note_numbering.reset_yearly', true)) {
            return false;
        }

        return $sequence->year === null || (int) $sequence->year !== $year;
    }

    private function format(int $value, int $year): string
    {
        $prefix = $this->billingSettings->creditNotePrefix();
        $separator = (string) config('corepanel.billing.credit_note_numbering.separator', '-');
        $padding = max(1, (int) config('corepanel.billing.credit_note_numbering.padding', 6));
        $includeYear = (bool) config('corepanel.billing.credit_note_numbering.include_year', true);

        $parts = [$prefix];

        if ($includeYear) {
            $parts[] = (string) $year;
        }

        $parts[] = str_pad((string) $value, $padding, '0', STR_PAD_LEFT);

        return implode($separator, $parts);
    }
}
