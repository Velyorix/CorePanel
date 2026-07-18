<?php

namespace Core\Billing\DataTransferObjects;

use Core\Billing\Enums\ProrataDirection;

/**
 * Day-based prorata result for a service plan change within a billing period.
 */
final readonly class ProrataResult
{
    public function __construct(
        public string $unusedCredit,
        public string $newCharge,
        public string $netAmount,
        public ProrataDirection $direction,
        public int $daysRemaining,
        public int $periodDays,
        public string $oldPeriodAmount,
        public string $newPeriodAmount,
    ) {
    }

    public function isCharge(): bool
    {
        return $this->direction === ProrataDirection::Charge;
    }

    public function isCredit(): bool
    {
        return $this->direction === ProrataDirection::Credit;
    }
}
