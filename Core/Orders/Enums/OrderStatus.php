<?php

namespace Core\Orders\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingPayment => __('Pending payment'),
        };
    }

    public function isPlaced(): bool
    {
        return $this === self::PendingPayment;
    }
}
