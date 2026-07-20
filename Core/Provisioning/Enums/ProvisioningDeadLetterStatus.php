<?php

namespace Core\Provisioning\Enums;

enum ProvisioningDeadLetterStatus: string
{
    case PendingReview = 'pending_review';
    case Requeued = 'requeued';
    case Resolved = 'resolved';
    case Discarded = 'discarded';

    public function isOpen(): bool
    {
        return $this === self::PendingReview;
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => __('Pending review'),
            self::Requeued => __('Requeued'),
            self::Resolved => __('Resolved'),
            self::Discarded => __('Discarded'),
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
