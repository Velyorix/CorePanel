<?php

namespace Core\Products\Enums;

use Carbon\CarbonInterface;
use InvalidArgumentException;

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

    /**
     * Standard catalog cycles (excludes custom, which needs an interval).
     *
     * @return list<self>
     */
    public static function standard(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $cycle): bool => $cycle !== self::Custom,
        ));
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

    /**
     * Hourly billing cycle is optional.
     */
    public function isOptional(): bool
    {
        return $this === self::Hourly;
    }

    public function isCustom(): bool
    {
        return $this === self::Custom;
    }

    public function isRecurring(): bool
    {
        return true;
    }

    /**
     * Approximate length in days for display / comparisons.
     * Custom requires an explicit interval.
     */
    public function days(?int $customIntervalDays = null): int
    {
        return match ($this) {
            self::Hourly => 1,
            self::Daily => 1,
            self::Weekly => 7,
            self::Monthly => 30,
            self::Quarterly => 90,
            self::SemiAnnual => 182,
            self::Annual => 365,
            self::Custom => $this->assertCustomInterval($customIntervalDays),
        };
    }

    /**
     * Calendar months covered by one period (null for sub-month cycles).
     */
    public function months(): ?int
    {
        return match ($this) {
            self::Hourly, self::Daily, self::Weekly, self::Custom => null,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::SemiAnnual => 6,
            self::Annual => 12,
        };
    }

    /**
     * Advance a date by one billing period.
     */
    public function addPeriod(CarbonInterface $from, ?int $customIntervalDays = null): CarbonInterface
    {
        return match ($this) {
            self::Hourly => $from->copy()->addHour(),
            self::Daily => $from->copy()->addDay(),
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonthNoOverflow(),
            self::Quarterly => $from->copy()->addMonthsNoOverflow(3),
            self::SemiAnnual => $from->copy()->addMonthsNoOverflow(6),
            self::Annual => $from->copy()->addYearNoOverflow(),
            self::Custom => $from->copy()->addDays($this->assertCustomInterval($customIntervalDays)),
        };
    }

    public function requiresCustomInterval(): bool
    {
        return $this === self::Custom;
    }

    private function assertCustomInterval(?int $customIntervalDays): int
    {
        if ($customIntervalDays === null || $customIntervalDays < 1) {
            throw new InvalidArgumentException(
                'Custom billing cycles require a positive custom_interval_days value.',
            );
        }

        return $customIntervalDays;
    }
}
