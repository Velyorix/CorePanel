<?php

namespace Core\Billing\Enums;

enum ProrataDirection: string
{
    case Charge = 'charge';
    case Credit = 'credit';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Charge => __('Charge'),
            self::Credit => __('Credit'),
            self::None => __('None'),
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
