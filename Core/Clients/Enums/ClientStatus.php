<?php

namespace Core\Clients\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
