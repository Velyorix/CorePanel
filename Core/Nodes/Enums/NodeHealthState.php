<?php

namespace Core\Nodes\Enums;

enum NodeHealthState: string
{
    case Online = 'online';
    case Degraded = 'degraded';
    case Offline = 'offline';
    case Unknown = 'unknown';
    case Skipped = 'skipped';

    public function allowsAllocation(): bool
    {
        return match ($this) {
            self::Online, self::Unknown => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Online => __('Online'),
            self::Degraded => __('Degraded'),
            self::Offline => __('Offline'),
            self::Unknown => __('Unknown'),
            self::Skipped => __('Skipped'),
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
