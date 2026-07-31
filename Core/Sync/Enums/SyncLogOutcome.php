<?php

namespace Core\Sync\Enums;

enum SyncLogOutcome: string
{
    case Polled = 'polled';
    case Resolved = 'resolved';
    case Diverged = 'diverged';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Synced = 'synced';

    public function label(): string
    {
        return match ($this) {
            self::Polled => __('In sync'),
            self::Resolved => __('Resolved'),
            self::Diverged => __('Diverged'),
            self::Failed => __('Failed'),
            self::Skipped => __('Skipped'),
            self::Synced => __('Synced'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Polled, self::Resolved, self::Synced => 'success',
            self::Diverged => 'warning',
            self::Failed => 'danger',
            self::Skipped => 'neutral',
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
