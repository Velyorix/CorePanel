<?php

namespace Core\Billing\Services;

use Carbon\CarbonInterface;
use Core\Billing\Contracts\RenewableBillableSource;
use Illuminate\Support\Collection;

/**
 * Default billable source before services provisioning is available.
 * Returns no renewal candidates.
 */
class NullRenewableBillableSource implements RenewableBillableSource
{
    public function dueForRenewal(CarbonInterface $asOf, int $daysBefore): Collection
    {
        return collect();
    }
}
