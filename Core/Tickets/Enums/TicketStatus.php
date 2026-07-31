<?php

namespace Core\Tickets\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Answered = 'answered';
    case Pending = 'pending';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::InProgress => __('In progress'),
            self::Answered => __('Answered'),
            self::Pending => __('Pending'),
            self::Closed => __('Closed'),
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
