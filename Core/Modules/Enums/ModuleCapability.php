<?php

namespace Core\Modules\Enums;

/**
 * High-level integration roles a module may declare in module.json.
 *
 * Product action capabilities (server.create, …) remain on ProductModuleCapability.
 */
enum ModuleCapability: string
{
    case ServerProvider = 'server_provider';
    case NodeProvider = 'node_provider';
    case PaymentGateway = 'payment_gateway';
    case NotificationChannel = 'notification_channel';
    case DnsProvider = 'dns_provider';
    case Other = 'other';

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
            self::ServerProvider => __('Server provider'),
            self::NodeProvider => __('Node provider'),
            self::PaymentGateway => __('Payment gateway'),
            self::NotificationChannel => __('Notification channel'),
            self::DnsProvider => __('DNS provider'),
            self::Other => __('Other'),
        };
    }
}
