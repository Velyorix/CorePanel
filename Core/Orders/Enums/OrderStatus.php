<?php

namespace Core\Orders\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    /** Roadmap "pending" — awaiting payment after checkout (10.8 / 11.x). */
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingPayment => __('Pending payment'),
            self::Paid => __('Paid'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function isPlaced(): bool
    {
        return $this !== self::Draft;
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Draft, self::PendingPayment => true,
            self::Paid, self::Cancelled => false,
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
