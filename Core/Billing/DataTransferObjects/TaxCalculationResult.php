<?php

namespace Core\Billing\DataTransferObjects;

final readonly class TaxCalculationResult
{
    public function __construct(
        public string $taxableAmount,
        public string $taxAmount,
        public string $taxRate,
        public string $taxLabel,
        public string $application,
        public bool $isReverseCharge,
    ) {
    }
}
