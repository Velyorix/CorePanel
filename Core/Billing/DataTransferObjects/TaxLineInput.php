<?php

namespace Core\Billing\DataTransferObjects;

final readonly class TaxLineInput
{
    public function __construct(
        public string $taxableAmount,
    ) {
    }
}
