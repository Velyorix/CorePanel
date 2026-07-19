<?php

namespace Core\Billing\Enums;

enum CreditNoteSettlement: string
{
    case Wallet = 'wallet';
    case PaymentRefund = 'payment_refund';

    public function label(): string
    {
        return match ($this) {
            self::Wallet => __('Client credit wallet'),
            self::PaymentRefund => __('Payment refund'),
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
