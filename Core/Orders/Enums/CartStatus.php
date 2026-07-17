<?php

namespace Core\Orders\Enums;

enum CartStatus: string
{
    case Open = 'open';
    case Converted = 'converted';
    case Abandoned = 'abandoned';

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
            self::Open => __('Open'),
            self::Converted => __('Converted'),
            self::Abandoned => __('Abandoned'),
        };
    }

    public function isMutable(): bool
    {
        return $this === self::Open;
    }
}
