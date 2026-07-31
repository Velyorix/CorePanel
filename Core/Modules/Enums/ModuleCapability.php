<?php

namespace Core\Modules\Enums;

/**
 * High-level integration roles a module may declare in module.json.
 *
 * Product action capabilities (server.create, …) remain on ProductModuleCapability.
 */
enum ModuleCapability: string
{
    case Extension = 'extension';
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

    /**
     * @return list<string>
     */
    public static function integrationValues(): array
    {
        return [
            self::ServerProvider->value,
            self::NodeProvider->value,
            self::PaymentGateway->value,
            self::DnsProvider->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function extensionValues(): array
    {
        return [
            self::Extension->value,
            self::NotificationChannel->value,
        ];
    }

    public static function isKnown(string $capability): bool
    {
        return self::tryFrom($capability) !== null;
    }

    public static function labelFor(string $capability): string
    {
        return self::tryFrom($capability)?->label() ?? $capability;
    }

    public function isIntegration(): bool
    {
        return in_array($this->value, self::integrationValues(), true);
    }

    public function isExtensionOriented(): bool
    {
        return in_array($this->value, self::extensionValues(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Extension => __('Extension'),
            self::ServerProvider => __('Server provider'),
            self::NodeProvider => __('Node provider'),
            self::PaymentGateway => __('Payment gateway'),
            self::NotificationChannel => __('Notification channel'),
            self::DnsProvider => __('DNS provider'),
            self::Other => __('Other'),
        };
    }
}
