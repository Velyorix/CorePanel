<?php

namespace Core\Billing\DataTransferObjects;

use Core\Billing\Enums\CouponType;

final readonly class DiscountResult
{
    public function __construct(
        public string $discountAmount,
        public string $discountedSubtotal,
        public CouponType $type,
        public string $value,
    ) {
    }
}
