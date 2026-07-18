<?php

namespace Core\Billing\DataTransferObjects;

use Core\Products\Enums\BillingCycle;

final readonly class QuoteLineInput
{
    /**
     * @param  array<string, mixed>|null  $options
     * @param  list<string>|null  $addons
     * @param  array<string, mixed>|null  $configData
     */
    public function __construct(
        public string $description,
        public string $unitPrice,
        public string $setupFee = '0.00',
        public int $quantity = 1,
        public ?int $productId = null,
        public ?string $productName = null,
        public ?string $productSlug = null,
        public ?BillingCycle $billingCycle = null,
        public ?int $customIntervalDays = null,
        public ?array $options = null,
        public ?array $addons = null,
        public ?array $configData = null,
    ) {
    }

    public function lineTotal(): string
    {
        $qty = max(1, $this->quantity);
        $total = ((float) $this->unitPrice * $qty) + (float) $this->setupFee;

        return number_format(round($total, 2), 2, '.', '');
    }
}
