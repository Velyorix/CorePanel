<?php

namespace Core\Orders\Enums;

enum OrderSource: string
{
    case ClientCheckout = 'client_checkout';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::ClientCheckout => __('Client checkout'),
            self::Admin => __('Admin'),
        };
    }
}
