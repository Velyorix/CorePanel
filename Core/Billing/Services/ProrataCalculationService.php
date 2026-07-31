<?php

namespace Core\Billing\Services;

use Core\Billing\DataTransferObjects\ProrataCalculationInput;
use Core\Billing\DataTransferObjects\ProrataResult;
use Core\Billing\Enums\ProrataDirection;
use Core\Billing\Exceptions\InvalidProrataException;
use Core\Products\Enums\BillingCycle;

/**
 * Pure prorata math for in-period service plan changes.
 *
 * Unused portion of the old plan is credited; the new plan is charged for the
 * same remaining days. Net difference is a charge (upgrade) or credit (downgrade).
 * Does not create invoices, credit notes, or mutate services.
 */
class ProrataCalculationService
{
    public function calculate(ProrataCalculationInput $input): ProrataResult
    {
        $this->assertCycleInterval($input->billingCycle, $input->customIntervalDays);

        $oldAmount = $this->assertNonNegativeMoney($input->oldPeriodAmount, 'oldPeriodAmount');
        $newAmount = $this->assertNonNegativeMoney($input->newPeriodAmount, 'newPeriodAmount');

        $periodStart = $input->periodStart->copy()->startOfDay();
        $periodEnd = $input->periodEnd->copy()->startOfDay();
        $changeAt = $input->changeAt->copy()->startOfDay();

        if ($periodEnd->lte($periodStart)) {
            throw new InvalidProrataException('Billing period end must be after period start.');
        }

        if ($changeAt->lt($periodStart)) {
            throw new InvalidProrataException('Change date cannot be before the billing period start.');
        }

        $periodDays = max(1, (int) $periodStart->diffInDays($periodEnd));
        $daysRemaining = $changeAt->gte($periodEnd)
            ? 0
            : max(0, (int) $changeAt->diffInDays($periodEnd));

        $fraction = $daysRemaining / $periodDays;

        $unusedCredit = $this->money($oldAmount * $fraction);
        $newCharge = $this->money($newAmount * $fraction);

        $net = round((float) $newCharge - (float) $unusedCredit, 2);

        if (abs($net) < 0.005) {
            return new ProrataResult(
                unusedCredit: $unusedCredit,
                newCharge: $newCharge,
                netAmount: '0.00',
                direction: ProrataDirection::None,
                daysRemaining: $daysRemaining,
                periodDays: $periodDays,
                oldPeriodAmount: $this->money($oldAmount),
                newPeriodAmount: $this->money($newAmount),
            );
        }

        if ($net > 0) {
            return new ProrataResult(
                unusedCredit: $unusedCredit,
                newCharge: $newCharge,
                netAmount: $this->money($net),
                direction: ProrataDirection::Charge,
                daysRemaining: $daysRemaining,
                periodDays: $periodDays,
                oldPeriodAmount: $this->money($oldAmount),
                newPeriodAmount: $this->money($newAmount),
            );
        }

        return new ProrataResult(
            unusedCredit: $unusedCredit,
            newCharge: $newCharge,
            netAmount: $this->money(abs($net)),
            direction: ProrataDirection::Credit,
            daysRemaining: $daysRemaining,
            periodDays: $periodDays,
            oldPeriodAmount: $this->money($oldAmount),
            newPeriodAmount: $this->money($newAmount),
        );
    }

    private function assertCycleInterval(BillingCycle $cycle, ?int $customIntervalDays): void
    {
        if (! $cycle->requiresCustomInterval()) {
            return;
        }

        try {
            $cycle->days($customIntervalDays);
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidProrataException($exception->getMessage(), 0, $exception);
        }
    }

    private function assertNonNegativeMoney(string $amount, string $field): float
    {
        if (! is_numeric($amount)) {
            throw new InvalidProrataException("{$field} must be a numeric money amount.");
        }

        $value = (float) $amount;

        if ($value < 0) {
            throw new InvalidProrataException("{$field} cannot be negative.");
        }

        return $value;
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
