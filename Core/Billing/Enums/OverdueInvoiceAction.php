<?php

namespace Core\Billing\Enums;

enum OverdueInvoiceAction: string
{
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Suspended => __('Suspended'),
            self::Terminated => __('Terminated'),
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
