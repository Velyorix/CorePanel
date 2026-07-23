<?php

namespace Core\Tickets\Services;

use Core\Billing\Models\BillingSequence;
use Core\Tickets\Models\Ticket;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ticket numbering (TK-YYYY-NNNNN by default).
 * Assigns numbers only — does not change ticket status.
 */
class TicketNumberService
{
    public const SEQUENCE_NAME = 'ticket';

    /**
     * Assign the next ticket number.
     * Idempotent when ticket_number is already set.
     */
    public function assignNumber(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket): Ticket {
            $locked = Ticket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (filled($locked->ticket_number)) {
                return $locked;
            }

            $number = $this->nextFormattedNumber();

            $locked->forceFill([
                'ticket_number' => $number,
            ])->save();

            return $locked->fresh(['client', 'category', 'assignee', 'messages.author']) ?? $locked;
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
            throw new RuntimeException('Ticket sequence produced an invalid value.');
        }

        $sequence->forceFill([
            'current_value' => $next,
            'year' => $year,
        ])->save();

        return $this->format($next, $year);
    }

    private function shouldReset(BillingSequence $sequence, int $year): bool
    {
        if (! (bool) config('corepanel.tickets.numbering.reset_yearly', true)) {
            return false;
        }

        return $sequence->year === null || (int) $sequence->year !== $year;
    }

    private function format(int $value, int $year): string
    {
        $prefix = (string) config('corepanel.tickets.numbering.prefix', 'TK');
        $separator = (string) config('corepanel.tickets.numbering.separator', '-');
        $padding = max(1, (int) config('corepanel.tickets.numbering.padding', 5));
        $includeYear = (bool) config('corepanel.tickets.numbering.include_year', true);

        $parts = [$prefix];

        if ($includeYear) {
            $parts[] = (string) $year;
        }

        $parts[] = str_pad((string) $value, $padding, '0', STR_PAD_LEFT);

        return implode($separator, $parts);
    }
}
