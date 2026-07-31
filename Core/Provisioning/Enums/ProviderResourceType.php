<?php

namespace Core\Provisioning\Enums;

enum ProviderResourceType: string
{
    case Server = 'server';
    case Volume = 'volume';
    case Network = 'network';
    case Database = 'database';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
