<?php

namespace Core\Billing\Enums;

enum ClientCreditTransactionType: string
{
    case Add = 'add';
    case Deduct = 'deduct';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Add => __('Credit added'),
            self::Deduct => __('Credit deducted'),
            self::Refund => __('Credit refund'),
        };
    }

    public function increasesBalance(): bool
    {
        return match ($this) {
            self::Add, self::Refund => true,
            self::Deduct => false,
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
