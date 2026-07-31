<?php

namespace Core\Billing\Contracts;

use Carbon\CarbonInterface;
use Core\Billing\DataTransferObjects\RenewalInvoiceInput;
use Illuminate\Support\Collection;

interface RenewableBillableSource
{
    /**
     * Billables whose renewal invoice should be generated as of $asOf
     * (typically next billing date within $daysBefore days).
     *
     * @return Collection<int, RenewalInvoiceInput>
     */
    public function dueForRenewal(CarbonInterface $asOf, int $daysBefore): Collection;
}
