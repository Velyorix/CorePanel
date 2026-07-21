<?php

namespace Core\Sync\Enums;

enum SyncLogSubject: string
{
    case Service = 'service';
    case Node = 'node';

    public function label(): string
    {
        return match ($this) {
            self::Service => __('Service'),
            self::Node => __('Node'),
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
