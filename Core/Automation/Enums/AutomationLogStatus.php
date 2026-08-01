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

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Running => __('Running'),
            self::Succeeded => __('Succeeded'),
            self::Failed => __('Failed'),
            self::Retrying => __('Retrying'),
            self::Fallback => __('Fallback'),
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
