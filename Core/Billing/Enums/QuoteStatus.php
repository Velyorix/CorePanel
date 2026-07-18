<?php

namespace Core\Billing\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Sent => __('Sent'),
            self::Accepted => __('Accepted'),
            self::Declined => __('Declined'),
            self::Expired => __('Expired'),
            self::Converted => __('Converted'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Draft, self::Sent, self::Accepted => true,
            self::Declined, self::Expired, self::Converted, self::Cancelled => false,
        };
    }

    public function isTerminal(): bool
    {
        return ! $this->isOpen();
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isConvertible(): bool
    {
        return match ($this) {
            self::Sent, self::Accepted => true,
            default => false,
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
