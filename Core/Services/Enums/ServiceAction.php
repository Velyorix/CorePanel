<?php

namespace Core\Services\Enums;

enum ServiceAction: string
{
    case Start = 'start';
    case Stop = 'stop';
    case Restart = 'restart';
    case Suspend = 'suspend';
    case Unsuspend = 'unsuspend';
    case Terminate = 'terminate';
    case Reinstall = 'reinstall';
    case Failover = 'failover';
    case Upgrade = 'upgrade';
    case Downgrade = 'downgrade';

    public function label(): string
    {
        return match ($this) {
            self::Start => __('Start'),
            self::Stop => __('Stop'),
            self::Restart => __('Restart'),
            self::Suspend => __('Suspend'),
            self::Unsuspend => __('Unsuspend'),
            self::Terminate => __('Terminate'),
            self::Reinstall => __('Reinstall'),
            self::Failover => __('Failover'),
            self::Upgrade => __('Upgrade'),
            self::Downgrade => __('Downgrade'),
        };
    }

    public function changesLifecycle(): bool
    {
        return match ($this) {
            self::Suspend, self::Unsuspend, self::Terminate => true,
            default => false,
        };
    }

    /**
     * Control actions allowed for a given service status.
     *
     * @return list<self>
     */
    public static function allowedFor(ServiceStatus $status): array
    {
        return match ($status) {
            ServiceStatus::Active => [
                self::Start,
                self::Stop,
                self::Restart,
                self::Suspend,
                self::Terminate,
                self::Reinstall,
            ],
            ServiceStatus::Suspended => [
                self::Unsuspend,
                self::Terminate,
            ],
            default => [],
        };
    }

    public function isAllowedFor(ServiceStatus $status): bool
    {
        return in_array($this, self::allowedFor($status), true);
    }

    /**
     * Client-area control actions (operational only).
     *
     * @return list<self>
     */
    public static function allowedForClient(ServiceStatus $status): array
    {
        $clientActions = [
            self::Start,
            self::Stop,
            self::Restart,
            self::Reinstall,
        ];

        return array_values(array_filter(
            self::allowedFor($status),
            fn (self $action): bool => in_array($action, $clientActions, true),
        ));
    }

    public function isAllowedForClient(ServiceStatus $status): bool
    {
        return in_array($this, self::allowedForClient($status), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
