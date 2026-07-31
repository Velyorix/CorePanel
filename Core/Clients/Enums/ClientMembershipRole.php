<?php

namespace Core\Clients\Enums;

enum ClientMembershipRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Support = 'support';
    case Billing = 'billing';
    case User = 'user';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::Manager => __('Manager'),
            self::Support => __('Support'),
            self::Billing => __('Billing'),
            self::User => __('User'),
        };
    }
}
