<?php

namespace Core\Billing\Services;

use Core\Billing\DataTransferObjects\DiscountResult;
use Core\Billing\Enums\CouponType;
use Core\Billing\Exceptions\InvalidCouponException;
use Core\Billing\Models\Coupon;

/**
 * Pure discount math for percent and fixed coupons.
 */
class DiscountCalculator
{
    public function calculate(string $subtotal, Coupon $coupon, ?string $currency = 'EUR'): DiscountResult
    {
        $base = max(0, (float) $subtotal);

        if ($coupon->type === CouponType::Percent) {
            $percent = (float) $coupon->value;

            if ($percent < 0 || $percent > 100) {
                throw new InvalidCouponException('Percent coupon value must be between 0 and 100.');
            }

            $discount = round($base * ($percent / 100), 2);
        } else {
            if ($coupon->currency !== null && $currency !== null
                && strtoupper((string) $coupon->currency) !== strtoupper($currency)
            ) {
                throw new InvalidCouponException('Coupon currency does not match the cart currency.');
            }

            $discount = min($base, max(0, (float) $coupon->value));
        }

        $discountMoney = $this->money($discount);
        $discounted = $this->money(max(0, $base - (float) $discountMoney));

        return new DiscountResult(
            discountAmount: $discountMoney,
            discountedSubtotal: $discounted,
            type: $coupon->type,
            value: number_format((float) $coupon->value, 2, '.', ''),
        );
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
