<?php

namespace Core\Modules\Enums;

/**
 * Built-in scaffold profiles for module:make.
 */
enum ModuleScaffoldProfile: string
{
    case Integration = 'integration';
    case Extension = 'extension';
    case PaymentGateway = 'payment_gateway';

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
            self::Integration => __('Integration module'),
            self::Extension => __('Extension module'),
            self::PaymentGateway => __('Payment gateway module'),
        };
    }

    /**
     * @return list<string>
     */
    public function defaultCapabilities(): array
    {
        return match ($this) {
            self::Integration => [ModuleCapability::Other->value],
            self::Extension => [
                ModuleCapability::Extension->value,
                ModuleCapability::NotificationChannel->value,
            ],
            self::PaymentGateway => [ModuleCapability::PaymentGateway->value],
        };
    }

    public static function fromInput(string $profile): self
    {
        $profile = strtolower(trim($profile));

        return self::tryFrom($profile)
            ?? throw new \InvalidArgumentException(
                'Profile ['.$profile.'] is invalid. Use integration, extension, or payment_gateway.',
            );
    }
}
