<?php

namespace Core\Services\DataTransferObjects;

use Core\Products\Enums\BillingCycle;

final readonly class ServicePlanChangeData
{
    /**
     * @param  array<string, mixed>|null  $configData
     */
    public function __construct(
        public int $targetProductId,
        public ?BillingCycle $billingCycle = null,
        public ?int $customIntervalDays = null,
        public ?array $configData = null,
        public bool $applyBilling = true,
    ) {
    }
}
