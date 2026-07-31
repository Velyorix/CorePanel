<?php

namespace Core\Products\Enums;

enum ProductCategoryStatus: string
{
    case Active = 'active';
    case Hidden = 'hidden';

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
            self::Active => __('Active'),
            self::Hidden => __('Hidden'),
        };
    }
}
