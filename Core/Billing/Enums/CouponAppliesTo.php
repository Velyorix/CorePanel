<?php

namespace Core\Billing\Enums;

enum CouponAppliesTo: string
{
    case Order = 'order';
    case Invoice = 'invoice';
    case Renewal = 'renewal';

    public function label(): string
    {
        return match ($this) {
            self::Order => __('Order'),
            self::Invoice => __('Invoice'),
            self::Renewal => __('Renewal'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
