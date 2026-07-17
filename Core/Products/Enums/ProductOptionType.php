<?php

namespace Core\Products\Enums;

enum ProductOptionType: string
{
    case Text = 'text';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Quantity = 'quantity';
    case Number = 'number';

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
            self::Text => __('Text'),
            self::Select => __('Select'),
            self::Checkbox => __('Checkbox'),
            self::Quantity => __('Quantity'),
            self::Number => __('Number'),
        };
    }

    public function hasChoices(): bool
    {
        return $this === self::Select;
    }
}
