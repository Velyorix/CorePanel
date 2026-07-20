<?php

namespace Core\Services\Enums;

enum ServiceStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Provisioning => __('Provisioning'),
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
            self::Terminated => __('Terminated'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Terminated, self::Cancelled => true,
            default => false,
        };
    }

    public function isBillable(): bool
    {
        return match ($this) {
            self::Active, self::Suspended => true,
            default => false,
        };
    }

    /**
     * Lifecycle graph for ServiceLifecycleService.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Provisioning, self::Cancelled],
            self::Provisioning => [self::Active, self::Failed, self::Cancelled],
            self::Active => [self::Suspended, self::Terminated],
            self::Suspended => [self::Active, self::Terminated],
            self::Failed => [self::Provisioning, self::Cancelled],
            self::Terminated, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
