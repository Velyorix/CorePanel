<?php

namespace Core\Billing\DataTransferObjects;

final readonly class TaxDocumentResult
{
    /**
     * @param  list<TaxCalculationResult>  $lines
     */
    public function __construct(
        public string $subtotal,
        public string $taxAmount,
        public string $total,
        public string $effectiveRate,
        public string $taxLabel,
        public string $application,
        public bool $isReverseCharge,
        public array $lines,
    ) {
    }
}
