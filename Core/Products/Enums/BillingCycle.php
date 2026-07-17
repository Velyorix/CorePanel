<?php

namespace Core\Products\Enums;

enum BillingCycle: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Hourly => __('Hourly'),
            self::Daily => __('Daily'),
            self::Weekly => __('Weekly'),
            self::Monthly => __('Monthly'),
            self::Quarterly => __('Quarterly'),
            self::SemiAnnual => __('Semi-annual'),
            self::Annual => __('Annual'),
            self::Custom => __('Custom'),
        };
    }
}
