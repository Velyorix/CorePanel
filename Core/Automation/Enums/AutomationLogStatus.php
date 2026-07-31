<?php

namespace Core\Automation\Enums;

enum AutomationLogStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Retrying = 'retrying';
    case Fallback = 'fallback';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
