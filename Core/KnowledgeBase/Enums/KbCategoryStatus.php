<?php

namespace Core\KnowledgeBase\Enums;

enum KbCategoryStatus: string
{
    case Active = 'active';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Hidden => __('Hidden'),
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
