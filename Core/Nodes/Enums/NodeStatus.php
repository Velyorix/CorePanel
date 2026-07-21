<?php

namespace Core\Nodes\Enums;

enum NodeStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Maintenance = 'maintenance';
    case Offline = 'offline';

    public function isSelectable(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Disabled => __('Disabled'),
            self::Maintenance => __('Maintenance'),
            self::Offline => __('Offline'),
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
