<?php

namespace Core\Billing\Enums;

enum TaxRuleType: string
{
    case Standard = 'standard';
    case Exempt = 'exempt';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
