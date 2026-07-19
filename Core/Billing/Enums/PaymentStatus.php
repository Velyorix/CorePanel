<?php

namespace Core\Billing\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Chargeback = 'chargeback';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
            self::Refunded => __('Refunded'),
            self::Chargeback => __('Chargeback'),
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Completed;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Refunded, self::Chargeback => true,
            self::Pending => false,
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
