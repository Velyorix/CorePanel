<?php

namespace Core\Billing\DataTransferObjects;

use Carbon\CarbonInterface;
use Core\Products\Enums\BillingCycle;

/**
 * Inputs for prorata on an in-period plan change (upgrade / downgrade).
 */
final readonly class ProrataCalculationInput
{
    public function __construct(
        public string $oldPeriodAmount,
        public string $newPeriodAmount,
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
        public CarbonInterface $changeAt,
        public BillingCycle $billingCycle,
        public ?int $customIntervalDays = null,
    ) {
    }
}
